<?php

namespace App\Filament\Support\Concerns;

use App\Enums\CqPartType;
use App\Enums\EditorMode;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Support\EditorModePreference;
use App\Models\Question;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

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

        if ($record?->question_type === QuestionType::Mcq) {
            $data['options'] = collect($record->options)
                ->map(fn (array $option) => [
                    ...$option,
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
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
     * The MCQ options Repeater's per-option `is_correct` checkbox is kept
     * as-is on each `options` entry (the form's own validation rule
     * guarantees exactly one is checked) — this just strips the UI-only
     * `has_image` flag so only `option`/`image`/`is_correct` are persisted.
     *
     * @param  array<string, mixed>  $data
     */
    protected function normalizeMcqOptions(array &$data): void
    {
        $questionType = $data['question_type'] ?? null;

        if (! in_array($questionType, [QuestionType::Mcq, QuestionType::Mcq->value], strict: true)) {
            return;
        }

        $data['options'] = collect($data['options'] ?? [])
            ->map(fn (array $option) => Arr::only($option, ['option', 'image', 'is_correct']))
            ->values()
            ->all();
    }

    /**
     * Saves whichever editor (Rich Text vs CKEditor) was used this time as
     * this user's preference (EditorModePreference), so their next Create
     * Question form defaults to it instead of always starting on Rich Text.
     *
     * @param  array<string, mixed>  $data
     */
    protected function rememberEditorModePreference(array $data): void
    {
        $editorMode = $data['editor_mode'] ?? null;
        $editorMode = $editorMode instanceof EditorMode ? $editorMode : EditorMode::tryFrom($editorMode ?? '');

        if ($editorMode) {
            EditorModePreference::remember(Auth::user(), $editorMode);
        }
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
