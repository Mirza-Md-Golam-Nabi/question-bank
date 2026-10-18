<?php

namespace App\Filament\Support;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\Question;
use App\Services\PastPaperJsonImporter;
use App\Services\QuestionJsonImporter;
use App\Services\QuestionJsonReader;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The "Add from JSON" button: paste the JSON, look the questions over as
 * they will appear, then add them all — on a chapter's question list
 * (make()) and on a past paper's MCQ or CQ list (forPastPaper()). Both are
 * the one window built by configure(); reading the JSON, judging it and
 * creating the questions is the importers' job (QuestionJsonImporter,
 * PastPaperJsonImporter), and who may use it the policies'.
 */
class QuestionJsonImportAction
{
    public static function make(Chapter $chapter): Action
    {
        $importer = app(QuestionJsonImporter::class);

        return static::configure(
            Action::make('importQuestionsFromJson')
                ->visible(fn (): bool => (bool) auth()->user()?->can('import', Question::class))
                ->modalDescription($chapter->name),
            guide: QuestionJsonImporter::formatGuide(),
            parse: fn (string $json, Get $get): Collection => $importer->parse($json, $chapter, $get('topic_id'), static::difficulty($get('difficulty'))),
            fields: [
                // The topic select lists the topics of whichever
                // chapter the form names.
                Hidden::make('chapter_id')->default($chapter->id),

                ContentHierarchySchema::topicSelect()
                    ->default(fn () => TopicPreference::for(auth()->user(), $chapter->id)),

                Select::make('difficulty')
                    ->label(__('Difficulty of questions that do not state one'))
                    ->options(Difficulty::class)
                    ->default(Difficulty::Easy)
                    ->required()
                    ->native(false),
            ],
        )->action(function (array $data, Action $action) use ($chapter, $importer): void {
            $result = static::importOrHalt(fn (): array => $importer->import(
                $data[QuestionJsonImporter::ERROR_KEY],
                $chapter,
                $data['topic_id'] ?? null,
                static::difficulty($data['difficulty']),
            ), $action);

            TopicPreference::remember(auth()->user(), $chapter->id, $data['topic_id'] ?? null);

            static::notifyAdded($result, trans_choice(':count was left out because it is already in this chapter.|:count were left out because they are already in this chapter.', $result['skipped']));
        });
    }

    /**
     * A past paper's list takes only its own kind of question, with
     * optional explanations (MCQ) or answers (CQ).
     */
    public static function forPastPaper(BoardQuestionPaper $paper, QuestionType $type): Action
    {
        $importer = app(PastPaperJsonImporter::class);

        return static::configure(
            Action::make('importPaperQuestionsFromJson')
                ->visible(fn (): bool => (bool) auth()->user()?->can('import', $paper))
                ->modalDescription($paper->title().' · '.$type->getLabel()),
            guide: PastPaperJsonImporter::reader($type)->formatGuide(),
            parse: fn (string $json): Collection => $importer->parse($json, $paper, $type),
        )->action(function (array $data, Action $action) use ($paper, $type, $importer): void {
            $result = static::importOrHalt(fn (): array => $importer->import($data[PastPaperJsonImporter::ERROR_KEY], $paper, $type), $action);

            static::notifyAdded($result, trans_choice(':count was left out because it is already in this paper.|:count were left out because they are already in this paper.', $result['skipped']));
        });
    }

    /**
     * The window both imports share: a first step with the import's own
     * fields, the format to copy and the JSON box (checked by `$parse`), and
     * a preview step showing the questions `$parse` builds.
     *
     * @param  Closure(string, Get): Collection<int, mixed>  $parse  Builds the questions, or throws a ValidationException listing every problem.
     * @param  array<Component>  $fields
     */
    protected static function configure(Action $action, string $guide, Closure $parse, array $fields = []): Action
    {
        return $action
            ->label(__('Add from JSON'))
            ->icon(Heroicon::OutlinedCodeBracket)
            ->color('gray')
            ->modalHeading(__('Add questions from JSON'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('Add questions'))
            ->steps([
                Step::make(__('Paste JSON'))
                    ->columns(2)
                    ->schema([
                        ...$fields,

                        View::make('filament.support.questions.json-import-format')
                            ->viewData(['guide' => $guide])
                            ->columnSpanFull(),

                        Textarea::make(QuestionJsonReader::ERROR_KEY)
                            ->label(__('Questions as JSON'))
                            ->helperText(__('"answer" is the number of the correct option. A formula goes between $ signs, and in JSON every backslash of it is written twice: :example. Images are added afterwards by editing the question.', ['example' => '$\\\\frac{1}{2}$']))
                            ->rows(12)
                            ->extraInputAttributes(['spellcheck' => 'false', 'dir' => 'ltr'])
                            ->required()
                            ->showAllValidationMessages()
                            ->rule(fn (Get $get): Closure => static::acceptableJsonRule($parse, $get))
                            ->columnSpanFull(),
                    ]),

                Step::make(__('Preview'))
                    ->schema([
                        // Holds how many formulas the browser could not
                        // draw — only the browser has KaTeX to find out.
                        // A convenience for the honest user, not a guard:
                        // everything that must hold is checked on the
                        // server by the rule above.
                        ViewField::make('unrenderable_formulas')
                            ->hiddenLabel()
                            ->view('filament.support.questions.json-import-preview')
                            ->viewData(fn (Get $get): array => [
                                'questions' => static::previewQuestions($parse, $get),
                            ])
                            ->default(0)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                                if ((int) $value > 0) {
                                    $fail(__('Some formulas could not be drawn. Go back, fix them in the JSON, and try again.'));
                                }
                            }),
                    ]),
            ]);
    }

    /**
     * Fails with every problem the importer finds in the JSON, each as its
     * own message.
     */
    protected static function acceptableJsonRule(Closure $parse, Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($parse, $get): void {
            try {
                $parse((string) $value, $get);
            } catch (ValidationException $exception) {
                foreach ($exception->errors()[QuestionJsonReader::ERROR_KEY] ?? [] as $problem) {
                    $fail($problem);
                }
            }
        };
    }

    /**
     * The questions to show on the preview step — none while the JSON is
     * still unacceptable, which the first step is already saying.
     *
     * @return Collection<int, mixed>
     */
    protected static function previewQuestions(Closure $parse, Get $get): Collection
    {
        if (blank($get(QuestionJsonReader::ERROR_KEY))) {
            return collect();
        }

        try {
            return $parse($get(QuestionJsonReader::ERROR_KEY), $get);
        } catch (ValidationException) {
            return collect();
        }
    }

    /**
     * Runs the import. The form already checked the JSON, so a refusal here
     * means something changed in between (the limit, a topic, the paper) —
     * shown as a notification, with the window kept open.
     *
     * @param  Closure(): array{added: int, skipped: int}  $import
     * @return array{added: int, skipped: int}
     */
    protected static function importOrHalt(Closure $import, Action $action): array
    {
        try {
            return $import();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('The questions could not be added'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $action->halt();
        }
    }

    /**
     * @param  array{added: int, skipped: int}  $result
     */
    protected static function notifyAdded(array $result, string $skippedMessage): void
    {
        Notification::make()
            ->title(trans_choice(':count question added|:count questions added', $result['added']))
            ->body($result['skipped'] > 0 ? $skippedMessage : null)
            ->success()
            ->send();
    }

    /**
     * A select of enum options hands back the enum itself or its value,
     * depending on where in the form's life it is read.
     */
    protected static function difficulty(Difficulty|string|null $difficulty): Difficulty
    {
        return $difficulty instanceof Difficulty ? $difficulty : (Difficulty::tryFrom((string) $difficulty) ?? Difficulty::Easy);
    }
}
