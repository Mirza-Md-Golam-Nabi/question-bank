<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\QuestionType;
use App\Filament\Support\DuplicateQuestionCheck;
use App\Filament\Support\PaperQuestionForms;
use App\Filament\Support\QuestionFormData;
use App\Filament\Support\StimulusGroupForm;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Services\DuplicateMatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaperMcqQuestionsRelationManager extends PaperQuestionsRelationManager
{
    protected static string $relationship = 'mcqQuestions';

    protected static ?string $title = 'MCQ';

    protected static ?string $modelLabel = 'MCQ question';

    protected function questionType(): QuestionType
    {
        return QuestionType::Mcq;
    }

    protected function listedRelations(): array
    {
        return ['stimulus'];
    }

    protected function inListOrder(Builder $query): Builder
    {
        return $query->reorder()->stimulusTogether('order_index');
    }

    protected function formComponents(): array
    {
        return PaperQuestionForms::mcq();
    }

    protected function createQuestion(array $data): Model
    {
        return $this->getRelationship()->create([...$data, 'options' => QuestionFormData::storedOptions($data['options'] ?? [])]);
    }

    protected function updateQuestion(Model $record, array $data): Model
    {
        $record->update([...$data, 'options' => QuestionFormData::storedOptions($data['options'] ?? [])]);

        return $record;
    }

    protected function fillForm(Model $record, array $data): array
    {
        return [...$data, 'options' => QuestionFormData::fillOptions($record->options)];
    }

    /**
     * "New stimulus with MCQs": a stimulus written once, with the MCQs that
     * share it — the same window as the question bank's.
     */
    protected function extraHeaderActions(): array
    {
        return [
            StimulusGroupForm::configureAction(
                Action::make('createStimulusGroup'),
                creating: true,
                imageDirectory: 'board-mcq',
                duplicates: fn (Get $get, ?Model $record): ?DuplicateMatch => DuplicateQuestionCheck::onPaperGroup($this->paper(), $get, $record),
            )
                ->color('gray')
                ->visible(fn (): bool => ! $this->isReadOnly())
                ->action(fn (array $data) => $this->saveGroup(null, $data)),
        ];
    }

    protected function extraRecordActions(): array
    {
        return [
            StimulusGroupForm::configureAction(
                Action::make('editStimulusGroup'),
                creating: false,
                imageDirectory: 'board-mcq',
                duplicates: fn (Get $get, ?Model $record): ?DuplicateMatch => DuplicateQuestionCheck::onPaperGroup($this->paper(), $get, $record),
            )
                ->iconButton()
                ->tooltip(__('Edit stimulus'))
                ->visible(fn (BoardMcqQuestion $record): bool => $record->isInStimulusGroup() && ! $this->isReadOnly())
                ->fillForm(fn (BoardMcqQuestion $record): array => StimulusGroupForm::fill(
                    $record->stimulus,
                    $record->stimulus->questions()->get(),
                ))
                ->action(fn (BoardMcqQuestion $record, array $data) => $this->saveGroup($record->stimulus, $data)),
        ];
    }

    /**
     * Saves a stimulus and its MCQs on the paper: new ones go to the end of
     * the list; an edited group keeps its place, and a question left out
     * of it is removed (a paper that has been sat can't be edited at all).
     *
     * @param  array<string, mixed>  $data
     */
    private function saveGroup(?BoardMcqStimulus $stimulus, array $data): void
    {
        DB::transaction(function () use ($stimulus, $data): void {
            $paper = $this->paper();

            $stimulus
                ? $stimulus->update(StimulusGroupForm::stimulus($data))
                : $stimulus = $paper->mcqStimuli()->create(StimulusGroupForm::stimulus($data));

            $questions = $stimulus->questions()->get()->keyBy('id');
            $place = $questions->min('order_index') ?? (int) $paper->mcqQuestions()->max('order_index') + 1;

            foreach (StimulusGroupForm::questionRows($data) as $row) {
                $question = $row['id'] ? $questions->pull($row['id']) : null;
                $attributes = [...$row['attributes'], 'stimulus_id' => $stimulus->id, 'order_index' => $place];

                $question ? $question->update($attributes) : $paper->mcqQuestions()->create($attributes);
            }

            BoardMcqQuestion::whereKey($questions->keys())->delete();
        });

        $this->noteChange($data);

        Notification::make()->title(__('Stimulus and questions saved'))->success()->send();
    }
}
