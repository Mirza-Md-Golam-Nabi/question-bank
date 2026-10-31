<?php

namespace App\Filament\Support;

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionType;
use App\Filament\Forms\Components\CkEditorField;
use App\Models\Question;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The full Question create/edit form, shared as-is by the Admin, Teacher,
 * and Staff QuestionResource — the only difference between panels is which
 * rows a panel's getEloquentQuery() lets a user reach, not the form itself.
 *
 * Its pieces — the text with its editor choice, image and preview, the MCQ
 * options, the CQ sub-questions — are also what a past paper's questions
 * are written with (PaperQuestionForms), so both are typed the same way.
 */
class QuestionFormSchema
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Grid::make(4)->columnSpanFull()->schema([
                static::textImageAndPreview('questions')->columnSpan(2),

                Group::make()
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        ...ContentHierarchySchema::components(),

                        Radio::make('question_type')
                            ->options(QuestionType::class)
                            ->default(QuestionType::Mcq)
                            ->live()
                            ->inline()
                            ->inlineLabel(false)
                            ->required(),

                        // The author's pick only stands until enough students
                        // have answered; after that the difficulty follows
                        // from their answers and is shown, not chosen.
                        Select::make('difficulty')
                            ->options(Difficulty::class)
                            ->default(Difficulty::Easy)
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Question $record): bool => (bool) $record?->hasCalculatedDifficulty())
                            ->helperText(fn (?Question $record): ?string => $record?->hasCalculatedDifficulty()
                                ? __('Set from the answers of :count students: :percent% got it right.', [
                                    'count' => $record->answer_count,
                                    'percent' => $record->correctAnswerPercent(),
                                ])
                                : null),

                        TextInput::make('marks')
                            ->numeric()
                            ->step(0.5)
                            ->minValue(0.5)
                            ->default(Question::DEFAULT_MCQ_MARKS)
                            ->required()
                            ->visible(fn (Get $get) => $get('question_type') === QuestionType::Mcq),

                        static::editorModeRadio(),
                    ]),
            ]),

            Group::make()
                ->columnSpanFull()
                ->live()
                ->schema(fn (Get $get) => match ($get('question_type')) {
                    QuestionType::Mcq => static::mcqComponents(),
                    QuestionType::Cq => static::cqComponents(),
                    default => [],
                }),

            DuplicateQuestionCheck::field(DuplicateQuestionCheck::inChapter(...)),
        ];
    }

    /**
     * The question's text in whichever editor is picked, its image, and a
     * live preview of both.
     *
     * @param  string|null  $label  Null: "Stimulus" for a CQ, "Question text" otherwise (by the form's `question_type`).
     */
    public static function textImageAndPreview(string $imageDirectory, ?string $label = null): Group
    {
        return Group::make()
            ->schema([
                Group::make()
                    ->live()
                    ->schema(fn (Get $get) => [
                        // Before the form is filled (as in an action's
                        // modal) the radio's own default applies, so the
                        // text never starts in the other editor's format.
                        static::questionTextComponent($get('editor_mode') ?? EditorModePreference::for(auth()->user()), $label),
                    ]),

                FileUpload::make('question_image')
                    ->label(__('Diagram / image (optional)'))
                    ->image()
                    ->disk('public')->visibility('public')
                    ->directory($imageDirectory)
                    ->live(),

                Group::make()
                    ->live()
                    ->schema(fn (Get $get) => [
                        // ->viewData() takes a Closure specifically
                        // so it stays lazy: calling $get() eagerly
                        // here (a plain array) reads a FileUpload's
                        // live state mid-hydration, which — isolated
                        // by bisection — corrupts this form's later
                        // default-state hydration and silently drops
                        // the `options` Repeater's ->defaultItems(4)
                        // to zero on first load.
                        View::make('filament.forms.components.question-preview')
                            ->viewData(fn (Get $get) => [
                                'html' => static::resolvePreviewHtml($get('question_text')),
                                'imageUrl' => static::resolveUploadedFileUrl($get('question_image')),
                            ]),
                    ]),
            ]);
    }

    /**
     * Rich Text or CKEditor for the question text.
     */
    public static function editorModeRadio(): Radio
    {
        return static::editorSwitch('editor_mode', 'question_text')
            ->label(__('Editor'))
            // Defaults to whichever editor this user saved a
            // question with last time (EditorModePreference),
            // falling back to Rich Text the very first time.
            // Only applies on Create — editing an existing
            // question fills this from the record itself,
            // overriding this default entirely.
            ->default(fn () => EditorModePreference::for(auth()->user()));
    }

    /**
     * The choice between the two editors for one piece of text, which is
     * then written in whichever is picked (textEditor()).
     */
    protected static function editorSwitch(string $name, string $textField): Radio
    {
        return Radio::make($name)
            ->options(EditorMode::class)
            ->live()
            ->inline()
            ->inlineLabel(false)
            // RichEditor's Tiptap-JSON live state and CKEditor's
            // plain-HTML state are incompatible formats — handing
            // one editor the other's leftover state on switch
            // silently breaks it, so always start clean instead.
            ->afterStateUpdated(fn (Set $set) => $set($textField, null))
            ->required();
    }

    /**
     * RichEditor and CkEditorField produce incompatible state formats
     * (Tiptap JSON vs plain HTML), so only the picked one is ever mounted
     * for a field. Both can hold tables.
     */
    protected static function textEditor(EditorMode|string|null $editorMode, string $name): RichEditor|CkEditorField
    {
        return ($editorMode instanceof EditorMode ? $editorMode : EditorMode::tryFrom((string) $editorMode)) === EditorMode::CkEditor
            ? CkEditorField::make($name)
            : RichEditor::make($name);
    }

    /**
     * `question_text` arrives here as either a plain HTML string
     * (CkEditorField always, or RichEditor once its state-cast has already
     * applied) or a raw Tiptap JSON document (RichEditor before that cast
     * applies, or a stale array briefly left over right after switching
     * `editor_mode`) — the shape itself says which it is, regardless of
     * which editor is currently active. `RichContentRenderer` is Filament's
     * own public API for turning Tiptap JSON into sanitized HTML.
     */
    public static function resolvePreviewHtml(mixed $questionText): ?string
    {
        if (blank($questionText)) {
            return null;
        }

        if (is_string($questionText)) {
            return $questionText;
        }

        return RichContentRenderer::make($questionText)->toHtml();
    }

    /**
     * FileUpload's live state is an array holding either a fresh
     * `TemporaryUploadedFile` (still being edited, not yet saved — needs its
     * own temporary preview URL) or a plain stored path string (loaded from
     * an existing record) — this mirrors how FileUpload resolves its own
     * inline thumbnail, so the preview panel shows the same image either way.
     */
    protected static function resolveUploadedFileUrl(mixed $state): ?string
    {
        $file = is_array($state) ? Arr::first($state) : $state;

        return match (true) {
            $file instanceof TemporaryUploadedFile => $file->temporaryUrl(),
            is_string($file) && filled($file) => Storage::disk('public')->url($file),
            default => null,
        };
    }

    /**
     * The question text in the editor the `editor_mode` radio picked.
     */
    protected static function questionTextComponent(EditorMode $editorMode, ?string $label = null): Component
    {
        $component = static::textEditor($editorMode, 'question_text');

        // CkEditorField syncs itself live via its own $wire.$set() call
        // (debounced 500ms in its Blade view); RichEditor needs the same
        // ->live() debounce here so the preview below it stays in sync.
        if ($component instanceof RichEditor) {
            $component->live(debounce: '500ms');
        }

        return $component
            ->label(fn (Get $get) => $label ?? ($get('question_type') === QuestionType::Cq ? __('Stimulus') : __('Question text')))
            // A question or a stimulus may be only its figure, so the text
            // is needed only when there is no image.
            ->required(fn (Get $get): bool => blank($get('question_image')))
            ->validationMessages(['required' => __('Write the text, or add an image.')])
            ->dehydrateStateUsing(fn (mixed $state): mixed => $state ?? '');
    }

    /**
     * An MCQ's options and its optional explanation — the same in the
     * question bank, on a past paper and under a shared stimulus.
     *
     * @return array<Component>
     */
    public static function mcqComponents(string $optionImageDirectory = 'question-options'): array
    {
        return [
            Repeater::make('options')
                ->label(__('Options'))
                ->schema(static::mcqOptionComponents($optionImageDirectory))
                ->columns(2)
                ->grid(4)
                ->addActionLabel(__('Add option'))
                ->defaultItems(4)
                ->minItems(Question::MIN_MCQ_OPTIONS)
                ->maxItems(Question::MAX_MCQ_OPTIONS)
                ->reorderable()
                ->live()
                ->required()
                ->rule(McqOptionsSchema::exactlyOneCorrectOptionRule()),

            ...static::explanationComponents(),
        ];
    }

    /**
     * An MCQ's optional explanation, under its heading the choice of its
     * own editor on one line — starting on the editor this user last wrote
     * an explanation in (EditorModePreference), whichever editor the
     * question text is in.
     *
     * @return array<Component>
     */
    protected static function explanationComponents(): array
    {
        return [
            static::editorSwitch('explanation_editor_mode', 'explanation')
                ->label(__('Explanation (optional)'))
                ->default(fn () => EditorModePreference::forExplanation(auth()->user()))
                // Left unpicked, the explanation is simply in Rich Text.
                ->required(false)
                ->columnSpanFull(),

            Group::make()
                ->columnSpanFull()
                ->live()
                ->schema(fn (Get $get) => [
                    static::textEditor($get('explanation_editor_mode') ?? EditorModePreference::forExplanation(auth()->user()), 'explanation')
                        ->hiddenLabel()
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function mcqOptionComponents(string $imageDirectory): array
    {
        return McqOptionsSchema::components(
            CkEditorField::make('option')
                ->label(__('Option'))
                ->compact()
                // An option can be only a picture (a figure to pick out),
                // so the text is needed only when there is no image.
                ->required(fn (Get $get): bool => blank($get('image')))
                ->validationMessages(['required' => __('Write the option, or add an image.')])
                ->columnSpanFull(),
            imageDirectory: $imageDirectory,
        );
    }

    /**
     * The four sub-questions, each with an image and its marks — and, for
     * a past paper's CQ, an optional answer.
     *
     * @return array<Component>
     */
    public static function cqComponents(string $imageDirectory = 'question-cq-parts', bool $withAnswers = false): array
    {
        return collect(CqPartType::ordered())
            ->map(fn (CqPartType $partType) => Section::make($partType->getLabel())
                ->schema(array_values(array_filter([
                    RichEditor::make("cq_parts.{$partType->value}.text")
                        ->label(__('Sub-question text'))
                        ->required(),
                    FileUpload::make("cq_parts.{$partType->value}.image")
                        ->label(__('Image (optional)'))
                        ->image()
                        ->disk('public')->visibility('public')
                        ->directory($imageDirectory),
                    TextInput::make("cq_parts.{$partType->value}.marks")
                        ->label(__('Marks'))
                        ->numeric()
                        ->step(0.5)
                        ->minValue(0.5)
                        ->default(1)
                        ->required(),
                    $withAnswers
                        ? CkEditorField::make("cq_parts.{$partType->value}.answer")
                            ->label(__('Answer (optional)'))
                            ->columnSpanFull()
                        : null,
                ])))
                ->columns(2))
            ->all();
    }
}
