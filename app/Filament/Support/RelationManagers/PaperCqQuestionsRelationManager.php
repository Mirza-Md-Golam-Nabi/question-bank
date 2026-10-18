<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\QuestionType;
use App\Filament\Support\PaperQuestionForms;
use App\Filament\Support\QuestionFormData;
use App\Models\BoardCqQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * A past paper's CQs. Its marks are never typed: they are the sum of the
 * sub-questions' (BoardCqQuestionPart keeps it in step).
 */
class PaperCqQuestionsRelationManager extends PaperQuestionsRelationManager
{
    protected static string $relationship = 'cqQuestions';

    protected static ?string $title = 'CQ';

    protected static ?string $modelLabel = 'CQ question';

    protected function questionType(): QuestionType
    {
        return QuestionType::Cq;
    }

    protected function formComponents(): array
    {
        return PaperQuestionForms::cq();
    }

    protected function createQuestion(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            /** @var BoardCqQuestion $question */
            $question = $this->getRelationship()->create(Arr::except($data, 'cq_parts'));

            return $this->syncParts($question, $data);
        });
    }

    protected function updateQuestion(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $record->update(Arr::except($data, 'cq_parts'));

            return $this->syncParts($record, $data);
        });
    }

    protected function fillForm(Model $record, array $data): array
    {
        return [...$data, 'cq_parts' => QuestionFormData::fillCqParts($record->parts, withAnswers: true)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncParts(BoardCqQuestion $question, array $data): BoardCqQuestion
    {
        $question->parts()->delete();

        foreach (QuestionFormData::cqPartRows($data['cq_parts'] ?? null, withAnswers: true) ?? [] as $part) {
            $question->parts()->create($part);
        }

        return $question;
    }
}
