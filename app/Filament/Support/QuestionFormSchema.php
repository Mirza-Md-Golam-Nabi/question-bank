<?php

namespace App\Filament\Support;

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionType;
use App\Filament\Forms\Components\CkEditorField;
use Closure;
use Filament\Forms\Components\Checkbox;
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
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The full Question create/edit form, shared as-is by the Admin, Teacher,
 * and Staff QuestionResource — the only difference between panels is which
 * rows a panel's getEloquentQuery() lets a user reach, not the form itself.
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
                Group::make()
                    ->columnSpan(2)
                    ->schema([
                        Group::make()
                            ->live()
                            ->schema(fn (Get $get) => [
                                static::questionTextComponent($get('editor_mode') ?? EditorMode::RichText),
                            ]),

                        FileUpload::make('question_image')
                            ->label('Diagram / image (optional)')
                            ->image()
                            ->disk('public')->visibility('public')
                            ->directory('questions')
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
                    ]),

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

                        Select::make('difficulty')
                            ->options(Difficulty::class)
                            ->default(Difficulty::Easy)
                            ->required()
                            ->native(false),

                        TextInput::make('marks')
                            ->numeric()
                            ->step(0.5)
                            ->minValue(0.5)
                            ->default(1)
                            ->required()
                            ->visible(fn (Get $get) => $get('question_type') === QuestionType::Mcq),

                        Radio::make('editor_mode')
                            ->label('Editor')
                            ->options(EditorMode::class)
                            // Defaults to whichever editor this user saved a
                            // question with last time (EditorModePreference),
                            // falling back to Rich Text the very first time.
                            // Only applies on Create — editing an existing
                            // question fills this from the record itself,
                            // overriding this default entirely.
                            ->default(fn () => EditorModePreference::for(auth()->user()))
                            ->live()
                            ->inline()
                            ->inlineLabel(false)
                            // RichEditor's Tiptap-JSON live state and CKEditor's
                            // plain-HTML state are incompatible formats — handing
                            // one editor the other's leftover state on switch
                            // silently breaks it, so always start clean instead.
                            ->afterStateUpdated(fn (Set $set) => $set('question_text', null))
                            ->required(),
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
        ];
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
    protected static function resolvePreviewHtml(mixed $questionText): ?string
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
     * RichEditor and CkEditorField both bind to `question_text`, but produce
     * incompatible state formats (Tiptap JSON vs plain HTML) — only one is
     * ever mounted at a time, picked by the `editor_mode` radio.
     */
    protected static function questionTextComponent(EditorMode $editorMode): Component
    {
        $component = $editorMode === EditorMode::CkEditor
            ? CkEditorField::make('question_text')
            // CkEditorField syncs itself live via its own $wire.$set() call
            // (debounced 500ms in its Blade view); RichEditor needs the same
            // ->live() debounce here so the preview below it stays in sync.
            : RichEditor::make('question_text')->live(debounce: '500ms');

        return $component
            ->label(fn (Get $get) => $get('question_type') === QuestionType::Cq ? 'উদ্দীপক (Stimulus)' : 'Question text')
            ->required();
    }

    /**
     * @return array<Component>
     */
    protected static function mcqComponents(): array
    {
        return [
            Repeater::make('options')
                ->label('Options')
                ->schema(static::mcqOptionComponents())
                ->columns(2)
                ->grid(4)
                ->addActionLabel('Add option')
                ->defaultItems(4)
                ->minItems(2)
                ->maxItems(6)
                ->reorderable()
                ->live()
                ->required()
                ->rule(static::exactlyOneCorrectOptionRule()),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function mcqOptionComponents(): array
    {
        return [
            CkEditorField::make('option')
                ->label('Option')
                ->compact()
                ->required()
                ->columnSpanFull(),

            Checkbox::make('is_correct')
                ->label('Correct answer')
                ->live()
                ->afterStateUpdated(function (bool $state, Checkbox $component, Get $get, Set $set) {
                    if (! $state) {
                        return;
                    }

                    $currentItemKey = (string) Str::of($component->getStatePath())
                        ->beforeLast('.is_correct')
                        ->afterLast('.');

                    collect($get('../'))
                        ->keys()
                        ->reject(fn ($itemKey) => (string) $itemKey === $currentItemKey)
                        ->each(fn ($itemKey) => $set("../{$itemKey}.is_correct", false));
                }),

            Checkbox::make('has_image')
                ->label('Add image')
                ->live()
                ->dehydrated(false),

            FileUpload::make('image')
                ->label('Image')
                ->image()
                ->disk('public')->visibility('public')
                ->directory('question-options')
                ->visible(fn (Get $get) => (bool) $get('has_image'))
                ->columnSpanFull(),
        ];
    }

    protected static function exactlyOneCorrectOptionRule(): Closure
    {
        // Filament evaluates whatever ->rule() is given through its own
        // closure-injection first (resolving parameters by name), which
        // can't resolve Laravel's $attribute/$value/$fail — so this outer,
        // zero-argument closure is what Filament calls, and its return
        // value (the real validator closure) is what Laravel then runs.
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
            if (collect($value)->contains(fn ($option) => (bool) ($option['is_correct'] ?? false))) {
                return;
            }

            $fail('Mark one option as the correct answer.');
        };
    }

    /**
     * @return array<Component>
     */
    protected static function cqComponents(): array
    {
        return collect(CqPartType::ordered())
            ->map(fn (CqPartType $partType) => Section::make($partType->getLabel())
                ->schema([
                    RichEditor::make("cq_parts.{$partType->value}.text")
                        ->label('Sub-question text')
                        ->required(),
                    FileUpload::make("cq_parts.{$partType->value}.image")
                        ->label('Image (optional)')
                        ->image()
                        ->disk('public')->visibility('public')
                        ->directory('question-cq-parts'),
                    TextInput::make("cq_parts.{$partType->value}.marks")
                        ->label('Marks')
                        ->numeric()
                        ->step(0.5)
                        ->minValue(0.5)
                        ->default(1)
                        ->required(),
                ])
                ->columns(2))
            ->all();
    }
}
