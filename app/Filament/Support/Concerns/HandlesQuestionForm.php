<?php

namespace App\Filament\Support\Concerns;

use App\Enums\CqPartType;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Question;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Shared by every panel's CreateQuestion/EditQuestion pages: extracting the
 * form's virtual `cq_parts.*` fields into real QuestionCqPart rows, and
 * routing an edit of an approved question through Question::createRevisionWith()
 * instead of a plain update — the one place this branching needs to live.
 */
trait HandlesQuestionForm
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $cqPartsData = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Question|null $record */
        $record = $this->getRecord();

        if ($record?->question_type === QuestionType::Cq) {
            foreach ($record->cqParts as $part) {
                $data['cq_parts'][$part->part_type->value] = [
                    'text' => $part->part_text,
                    'image' => $part->part_image,
                    'marks' => $part->marks,
                ];
            }
        }

        // The MCQ options Repeater has no separate `correct_answer` field —
        // each option carries its own `is_correct` checkbox instead. Since
        // that's not a real persisted column, it's derived here (once, in
        // plain PHP) rather than via a per-field Filament hydration hook.
        if ($record?->question_type === QuestionType::Mcq) {
            $data['options'] = collect($record->options)
                ->map(fn (array $option) => [
                    ...$option,
                    'is_correct' => $option['option'] === $record->correct_answer,
                    'has_image' => filled($option['image'] ?? null),
                ])
                ->all();
        }

        return $data;
    }

    /**
     * Pulls the `cq_parts` virtual field out of the form data (it has no
     * matching column on `questions`) and normalizes it into a list of
     * QuestionCqPart-ready attribute arrays, stashed for the caller to
     * persist once the parent Question row exists.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>|null
     */
    protected function extractCqPartsData(array &$data): ?array
    {
        $cqParts = $data['cq_parts'] ?? null;
        unset($data['cq_parts']);

        if (! $cqParts) {
            return null;
        }

        return collect(CqPartType::ordered())
            ->filter(fn (CqPartType $type) => filled($cqParts[$type->value]['text'] ?? null))
            ->map(fn (CqPartType $type) => [
                'part_type' => $type,
                'part_order' => $type->order(),
                'part_text' => $cqParts[$type->value]['text'],
                'part_image' => $cqParts[$type->value]['image'] ?? null,
                'marks' => $cqParts[$type->value]['marks'] ?? 1,
            ])
            ->values()
            ->all();
    }

    /**
     * The MCQ options Repeater carries a per-option `is_correct` checkbox
     * instead of a separate `correct_answer` field — this derives
     * `correct_answer` from whichever option is checked (the form's own
     * validation rule guarantees exactly one is), then strips `is_correct`
     * so only `option`/`image` land in the persisted `options` JSON.
     *
     * @param  array<string, mixed>  $data
     */
    protected function normalizeMcqOptions(array &$data): void
    {
        $questionType = $data['question_type'] ?? null;

        if (! in_array($questionType, [QuestionType::Mcq, QuestionType::Mcq->value], strict: true)) {
            return;
        }

        $options = collect($data['options'] ?? []);

        $data['correct_answer'] = $options->first(fn (array $option) => (bool) ($option['is_correct'] ?? false))['option'] ?? null;

        $data['options'] = $options
            ->map(fn (array $option) => Arr::only($option, ['option', 'image']))
            ->values()
            ->all();
    }

    protected function syncCqParts(Question $question): void
    {
        if ($this->cqPartsData === null) {
            return;
        }

        $question->cqParts()->delete();

        foreach ($this->cqPartsData as $partData) {
            $question->cqParts()->create($partData);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleApprovedQuestionEdit(Question $record, array $data): Model
    {
        $revision = $record->createRevisionWith($data, $this->cqPartsData);

        Notification::make()
            ->title('Approved question edited')
            ->body('A new pending version has been created for re-review; the previous approved version is unchanged.')
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('edit', ['record' => $revision]));

        return $revision;
    }

    protected function isEditingAnApprovedQuestion(Question $record): bool
    {
        return $record->status === QuestionStatus::Approved;
    }
}
