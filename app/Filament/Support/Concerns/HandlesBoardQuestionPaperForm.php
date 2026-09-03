<?php

namespace App\Filament\Support\Concerns;

use App\Enums\CqPartType;
use App\Models\BoardQuestionPaper;
use Illuminate\Support\Arr;

/**
 * Shared by the BoardQuestionPaperResource's Create/Edit pages: the
 * `mcq_questions`/`cq_questions` (and each CQ question's `cq_parts.*`)
 * virtual fields have no matching columns on `board_question_papers`, so
 * they're extracted from the form data and persisted explicitly — a whole
 * paper is replaced/synced as one unit on every save, since (unlike the
 * regular question bank) there's no per-question versioning here.
 */
trait HandlesBoardQuestionPaperForm
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var BoardQuestionPaper|null $record */
        $record = $this->getRecord();

        if (! $record) {
            return $data;
        }

        $data['mcq_questions'] = $record->mcqQuestions->map(fn ($mcq) => [
            'question_text' => $mcq->question_text,
            'question_image' => $mcq->question_image,
            // The options Repeater has no separate `correct_answer` field —
            // each option carries its own `is_correct` checkbox instead, so
            // it's derived here (once, in plain PHP) from the persisted
            // `correct_answer` rather than via a per-field hydration hook.
            'options' => collect($mcq->options)
                ->map(fn (array $option) => [
                    ...$option,
                    'is_correct' => $option['option'] === $mcq->correct_answer,
                    'has_image' => filled($option['image'] ?? null),
                ])
                ->all(),
            'marks' => $mcq->marks,
        ])->all();

        $data['cq_questions'] = $record->cqQuestions->map(function ($cq) {
            $partsData = [];

            foreach ($cq->parts as $part) {
                $partsData[$part->part_type->value] = ['text' => $part->part_text, 'marks' => $part->marks];
            }

            return [
                'question_text' => $cq->question_text,
                'question_image' => $cq->question_image,
                'cq_parts' => $partsData,
            ];
        })->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    protected function extractQuestionsData(array &$data): array
    {
        $mcqQuestions = $data['mcq_questions'] ?? [];
        $cqQuestions = $data['cq_questions'] ?? [];
        unset($data['mcq_questions'], $data['cq_questions']);

        $normalizedMcqQuestions = collect($mcqQuestions)
            ->map(function (array $mcqQuestion) {
                $options = collect($mcqQuestion['options'] ?? []);

                $mcqQuestion['correct_answer'] = $options->first(fn (array $option) => (bool) ($option['is_correct'] ?? false))['option'] ?? null;

                $mcqQuestion['options'] = $options
                    ->map(fn (array $option) => Arr::only($option, ['option', 'image']))
                    ->values()
                    ->all();

                return $mcqQuestion;
            })
            ->values()
            ->all();

        $normalizedCqQuestions = collect($cqQuestions)
            ->map(function (array $cqQuestion) {
                $cqParts = $cqQuestion['cq_parts'] ?? [];

                $cqQuestion['parts'] = collect(CqPartType::ordered())
                    ->filter(fn (CqPartType $type) => filled($cqParts[$type->value]['text'] ?? null))
                    ->map(fn (CqPartType $type) => [
                        'part_type' => $type,
                        'part_order' => $type->order(),
                        'part_text' => $cqParts[$type->value]['text'],
                        'marks' => $cqParts[$type->value]['marks'] ?? 1,
                    ])
                    ->values()
                    ->all();

                unset($cqQuestion['cq_parts']);

                return $cqQuestion;
            })
            ->values()
            ->all();

        return [$normalizedMcqQuestions, $normalizedCqQuestions];
    }

    /**
     * @param  array<int, array<string, mixed>>  $mcqQuestionsData
     * @param  array<int, array<string, mixed>>  $cqQuestionsData
     */
    protected function syncQuestions(BoardQuestionPaper $paper, array $mcqQuestionsData, array $cqQuestionsData): void
    {
        $paper->mcqQuestions()->delete();
        $paper->cqQuestions()->delete();

        foreach ($mcqQuestionsData as $index => $mcqData) {
            $paper->mcqQuestions()->create([...$mcqData, 'order_index' => $index]);
        }

        foreach ($cqQuestionsData as $index => $cqData) {
            $parts = $cqData['parts'] ?? [];
            unset($cqData['parts']);

            $cqQuestion = $paper->cqQuestions()->create([...$cqData, 'order_index' => $index]);

            foreach ($parts as $partData) {
                $cqQuestion->parts()->create($partData);
            }
        }
    }
}
