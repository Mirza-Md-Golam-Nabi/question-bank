<?php

namespace App\Filament\Support;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\Question;
use App\Services\DuplicateMatch;
use App\Services\ImportedImages;
use App\Services\ImportedQuestions;
use App\Services\PastPaperJsonImporter;
use App\Services\QuestionDuplicateScreening;
use App\Services\QuestionJsonImporter;
use App\Services\QuestionJsonReader;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
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
use WeakMap;

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
    /**
     * The preview step's field of possible duplicates to add anyway.
     */
    public const ACCEPTED_FIELD = 'accepted_duplicates';

    /**
     * The preview's work, per Livewire component — so per request — and
     * per JSON.
     *
     * @var WeakMap<object, array<string, array{questions: Collection<int, mixed>, matches: array<int, DuplicateMatch>, notTaken: array<string, string>}>>|null
     */
    protected static ?WeakMap $reviews = null;

    public static function make(Chapter $chapter): Action
    {
        $importer = app(QuestionJsonImporter::class);

        return static::configure(
            Action::make('importQuestionsFromJson')
                ->visible(fn (): bool => (bool) auth()->user()?->can('import', Question::class))
                ->modalDescription($chapter->name),
            guide: QuestionJsonImporter::formatGuide(),
            parse: fn (string $json, Get $get, ImportedImages $images): Collection => $importer->parse($json, $chapter, $get('topic_id'), static::difficulty($get('difficulty')), $images),
            duplicates: fn (Collection $questions): array => $importer->duplicates($questions, $chapter),
            import: function (string $json, array $data, array $accepted, ImportedImages $images) use ($chapter, $importer): array {
                $result = $importer->import($json, $chapter, $data['topic_id'] ?? null, static::difficulty($data['difficulty']), $accepted, $images);

                TopicPreference::remember(auth()->user(), $chapter->id, $data['topic_id'] ?? null);

                return $result;
            },
            leavesDuplicatesToAdmin: fn (): bool => QuestionDuplicateScreening::screensQuestionsBy(auth()->user()),
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
        );
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
            parse: fn (string $json, Get $get, ImportedImages $images): Collection => $importer->parse($json, $paper, $type, $images),
            duplicates: fn (Collection $questions): array => $importer->duplicates($questions, $paper, $type),
            import: fn (string $json, array $data, array $accepted, ImportedImages $images): array => $importer->import($json, $paper, $type, $accepted, $images),
        );
    }

    /**
     * The window both imports share: a first step with the import's own
     * fields, the format to copy, the JSON box and the box for its images
     * (checked by `$parse`), and a preview step showing the questions
     * `$parse` builds — the images not taken listed by name, each question
     * that is already there, or may be (`$duplicates`), beside the one it
     * looks like, and the possible duplicates to tick to add anyway. When
     * the Admin is to decide on the questions already there as they are
     * (`$leavesDuplicatesToAdmin`), those are added rather than left out —
     * the preview says so — and only the possible duplicates are ticked.
     * Submitting runs `$import` and says what came of it.
     *
     * @param  Closure(string, Get, ImportedImages): Collection<int, mixed>  $parse  Builds the questions with their images, or throws a ValidationException listing every problem.
     * @param  Closure(Collection<int, mixed>): array<int, DuplicateMatch>  $duplicates  Which of them look like questions already there.
     * @param  Closure(string, array<string, mixed>, array<int, int>, ImportedImages): array{added: int, skipped: int, possible_duplicates: int}  $import  Adds the questions of the JSON, given the form's data, the possible duplicates accepted and the images.
     * @param  (Closure(): bool)|null  $leavesDuplicatesToAdmin  Whether a question already there as it is is added, waiting for the Admin.
     * @param  array<Component>  $fields
     */
    protected static function configure(Action $action, string $guide, Closure $parse, Closure $duplicates, Closure $import, ?Closure $leavesDuplicatesToAdmin = null, array $fields = []): Action
    {
        $leftToAdmin = fn (): bool => $leavesDuplicatesToAdmin !== null && $leavesDuplicatesToAdmin();

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
                            ->helperText(__('"answer" is the number of the correct option. A formula goes between $ signs, and in JSON every backslash of it is written twice: :example. A figure is an image file named in "image" and given below.', ['example' => '$\\\\frac{1}{2}$']))
                            ->rows(12)
                            ->extraInputAttributes(['spellcheck' => 'false', 'dir' => 'ltr'])
                            ->required()
                            ->showAllValidationMessages()
                            ->rule(fn (Get $get): Closure => static::acceptableJsonRule($parse, $get))
                            ->columnSpanFull(),

                        // Kept as uploads until the questions are added;
                        // what is in them is ImportedImages' to judge.
                        FileUpload::make(ImportedQuestions::IMAGES_KEY)
                            ->label(__('Images (optional)'))
                            ->helperText(__('The figures the JSON names in "image" — one ZIP, or the image files themselves (PNG, JPG, WebP), found by their file names.'))
                            ->multiple()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/zip', 'application/x-zip-compressed'])
                            ->maxSize(ImportedImages::UPLOAD_CEILING_KB)
                            ->previewable(false)
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
                            ->viewData(fn (Get $get, object $livewire): array => [...static::review($parse, $duplicates, $get, $livewire), 'leftToAdmin' => $leftToAdmin()])
                            ->default(0)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                                if ((int) $value > 0) {
                                    $fail(__('Some formulas could not be drawn. Go back, fix them in the JSON, and try again.'));
                                }
                            }),

                        CheckboxList::make(self::ACCEPTED_FIELD)
                            ->label(__('Possible duplicates to add anyway'))
                            ->helperText(__('Each is shown above beside the question it looks like. One left unticked is not added.'))
                            ->options(fn (Get $get, object $livewire): array => static::possibleDuplicateOptions(static::review($parse, $duplicates, $get, $livewire), $leftToAdmin()))
                            ->allowHtml()
                            ->visible(fn (Get $get, object $livewire): bool => static::possibleDuplicateOptions(static::review($parse, $duplicates, $get, $livewire), $leftToAdmin()) !== [])
                            ->bulkToggleable()
                            ->selectAllAction(fn (Action $action): Action => $action->label(__('Accept all')))
                            ->deselectAllAction(fn (Action $action): Action => $action->label(__('Accept none'))),
                    ]),
            ])
            ->action(function (array $data, Action $action) use ($import): void {
                $images = static::images($data[ImportedQuestions::IMAGES_KEY] ?? null);

                $result = static::importOrHalt(fn (): array => $import($data[QuestionJsonReader::ERROR_KEY], $data, static::accepted($data), $images), $action);

                static::notifyAdded($result, $images);
            });
    }

    /**
     * Fails with every problem the importer finds in the JSON and in the
     * images it names, each as its own message — an image not given or
     * not taken by its file name.
     */
    protected static function acceptableJsonRule(Closure $parse, Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($parse, $get): void {
            try {
                $parse((string) $value, $get, static::images($get(ImportedQuestions::IMAGES_KEY)));
            } catch (ValidationException $exception) {
                $errors = $exception->errors();

                foreach ([...$errors[QuestionJsonReader::ERROR_KEY] ?? [], ...$errors[ImportedQuestions::IMAGES_KEY] ?? []] as $problem) {
                    $fail($problem);
                }
            }
        };
    }

    /**
     * The images in the upload box.
     */
    protected static function images(mixed $files): ImportedImages
    {
        return ImportedImages::fromUploads(is_iterable($files) ? $files : []);
    }

    /**
     * The questions to show on the preview step, and which of them look
     * like questions already there — none while the JSON is still
     * unacceptable, which the first step is already saying. Worked out once
     * per request for the same JSON, however many parts of the step ask.
     *
     * @return array{questions: Collection<int, mixed>, matches: array<int, DuplicateMatch>, notTaken: array<string, string>}
     */
    protected static function review(Closure $parse, Closure $duplicates, Get $get, object $livewire): array
    {
        $json = (string) $get(QuestionJsonReader::ERROR_KEY);
        $images = static::images($get(ImportedQuestions::IMAGES_KEY));
        $key = implode('|', [spl_object_id($parse), md5($json), $images->key(), $get('topic_id'), static::difficulty($get('difficulty'))->value]);

        static::$reviews ??= new WeakMap;
        $reviews = static::$reviews[$livewire] ?? [];

        if (! isset($reviews[$key])) {
            try {
                $questions = blank($json) ? collect() : $parse($json, $get, $images);
            } catch (ValidationException) {
                $questions = collect();
            }

            $reviews[$key] = [
                'questions' => $questions,
                'matches' => $questions->isEmpty() ? [] : $duplicates($questions),
                'notTaken' => $questions->isEmpty() ? [] : $images->notTaken(),
            ];
            static::$reviews[$livewire] = $reviews;
        }

        return $reviews[$key];
    }

    /**
     * The possible duplicates to tick, by the place of their (unit's first)
     * question: "Question 12 — 92% alike", with a button that jumps to that
     * question in the preview above.
     *
     * @param  array{questions: Collection<int, mixed>, matches: array<int, DuplicateMatch>, notTaken: array<string, string>}  $review
     * @param  bool  $leftToAdmin  Whether the questions already there as they are are added (so not asked about).
     * @return array<int, string>
     */
    protected static function possibleDuplicateOptions(array $review, bool $leftToAdmin = false): array
    {
        $matches = $leftToAdmin ? ImportedQuestions::withoutExact($review['matches']) : $review['matches'];

        return collect(ImportedQuestions::units($review['questions']))
            ->filter(fn (array $unit): bool => ImportedQuestions::isPossibleDuplicate($unit, $matches))
            ->map(fn (array $unit): string => view('filament.support.questions.duplicate-option', [
                'label' => __('Question :numbers — :percent% alike', [
                    'numbers' => count($unit) > 1 ? ($unit[0] + 1).'–'.(end($unit) + 1) : $unit[0] + 1,
                    'percent' => collect($unit)->map(fn (int $index): int => ($review['matches'][$index] ?? null)?->percent ?? 0)->max(),
                ]),
                'anchor' => static::previewAnchor($unit[0] + 1),
            ])->render())
            ->all();
    }

    /**
     * The id of a question's box in the preview, by its number there.
     */
    public static function previewAnchor(int $number): string
    {
        return "qb-import-question-{$number}";
    }

    /**
     * The possible duplicates the user ticked.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    protected static function accepted(array $data): array
    {
        return array_map('intval', $data[self::ACCEPTED_FIELD] ?? []);
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
     * @param  array{added: int, skipped: int, possible_duplicates: int}  $result
     */
    protected static function notifyAdded(array $result, ImportedImages $images): void
    {
        $notTaken = array_keys($images->notTaken());

        $leftOut = array_filter([
            $result['skipped'] > 0 ? trans_choice(':count was left out because the same question is already there, or earlier in the JSON.|:count were left out because the same questions are already there, or earlier in the JSON.', $result['skipped']) : null,
            $result['possible_duplicates'] > 0 ? trans_choice(':count possible duplicate was not added.|:count possible duplicates were not added.', $result['possible_duplicates']) : null,
            $notTaken !== [] ? __('Images not taken: :names.', ['names' => implode(', ', $notTaken)]) : null,
        ]);

        Notification::make()
            ->title(trans_choice(':count question added|:count questions added', $result['added']))
            ->body($leftOut === [] ? null : implode(' ', $leftOut))
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
