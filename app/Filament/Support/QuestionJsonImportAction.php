<?php

namespace App\Filament\Support;

use App\Enums\Difficulty;
use App\Models\Chapter;
use App\Models\Question;
use App\Services\QuestionJsonImporter;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The "Add from JSON" button of a chapter's question list: paste the JSON,
 * look the questions over as they will appear, then add them all. Only the
 * window is built here — reading the JSON, judging it and creating the
 * questions is QuestionJsonImporter's job, and who may use it is
 * QuestionPolicy::import()'s.
 */
class QuestionJsonImportAction
{
    public static function make(Chapter $chapter): Action
    {
        return Action::make('importQuestionsFromJson')
            ->label(__('Add from JSON'))
            ->icon(Heroicon::OutlinedCodeBracket)
            ->color('gray')
            ->visible(fn (): bool => (bool) auth()->user()?->can('import', Question::class))
            ->modalHeading(__('Add questions from JSON'))
            ->modalDescription($chapter->name)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('Add questions'))
            ->steps([
                Step::make(__('Paste JSON'))
                    ->columns(2)
                    ->schema([
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

                        View::make('filament.support.questions.json-import-format')
                            ->viewData(['guide' => QuestionJsonImporter::formatGuide()])
                            ->columnSpanFull(),

                        Textarea::make(QuestionJsonImporter::ERROR_KEY)
                            ->label(__('Questions as JSON'))
                            ->helperText(__('"answer" is the number of the correct option. A formula goes between $ signs, and in JSON every backslash of it is written twice: :example. Images are added afterwards by editing the question.', ['example' => '$\\\\frac{1}{2}$']))
                            ->rows(12)
                            ->extraInputAttributes(['spellcheck' => 'false', 'dir' => 'ltr'])
                            ->required()
                            ->showAllValidationMessages()
                            ->rule(fn (Get $get): Closure => static::acceptableJsonRule($chapter, $get))
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
                                'questions' => static::previewQuestions($chapter, $get),
                            ])
                            ->default(0)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                                if ((int) $value > 0) {
                                    $fail(__('Some formulas could not be drawn. Go back, fix them in the JSON, and try again.'));
                                }
                            }),
                    ]),
            ])
            ->action(function (array $data, Action $action) use ($chapter): void {
                try {
                    $result = app(QuestionJsonImporter::class)->import(
                        $data[QuestionJsonImporter::ERROR_KEY],
                        $chapter,
                        $data['topic_id'] ?? null,
                        static::difficulty($data['difficulty']),
                    );
                } catch (ValidationException $exception) {
                    // Already checked by the form, so this is only reached
                    // if something changed in between (the limit, a topic).
                    Notification::make()
                        ->title(__('The questions could not be added'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                TopicPreference::remember(auth()->user(), $chapter->id, $data['topic_id'] ?? null);

                Notification::make()
                    ->title(trans_choice(':count question added|:count questions added', $result['added']))
                    ->body($result['skipped'] > 0
                        ? trans_choice(':count was left out because it is already in this chapter.|:count were left out because they are already in this chapter.', $result['skipped'])
                        : null)
                    ->success()
                    ->send();
            });
    }

    /**
     * Fails with every problem the importer finds in the JSON, each as its
     * own message.
     */
    protected static function acceptableJsonRule(Chapter $chapter, Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($chapter, $get): void {
            try {
                app(QuestionJsonImporter::class)->parse((string) $value, $chapter, $get('topic_id'), static::difficulty($get('difficulty')));
            } catch (ValidationException $exception) {
                foreach ($exception->errors()[QuestionJsonImporter::ERROR_KEY] ?? [] as $problem) {
                    $fail($problem);
                }
            }
        };
    }

    /**
     * The questions to show on the preview step — none while the JSON is
     * still unacceptable, which the first step is already saying.
     *
     * @return Collection<int, Question>
     */
    protected static function previewQuestions(Chapter $chapter, Get $get): Collection
    {
        if (blank($get(QuestionJsonImporter::ERROR_KEY))) {
            return collect();
        }

        try {
            return app(QuestionJsonImporter::class)->parse($get(QuestionJsonImporter::ERROR_KEY), $chapter, $get('topic_id'), static::difficulty($get('difficulty')));
        } catch (ValidationException) {
            return collect();
        }
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
