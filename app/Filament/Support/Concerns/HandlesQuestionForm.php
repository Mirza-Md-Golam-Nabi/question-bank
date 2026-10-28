<?php

namespace App\Filament\Support\Concerns;

use App\Enums\QuestionType;
use App\Filament\Support\EditorModePreference;
use App\Filament\Support\QuestionFormData;
use App\Filament\Support\TopicPreference;
use App\Models\Question;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by every panel's CreateQuestion/EditQuestion pages: extracting the
 * form's virtual `cq_parts.*` fields into real QuestionCqPart rows, and
 * routing an edit that makes a new version (Question::editsMakeNewVersion())
 * through Question::createRevisionWith() instead of a plain update — the
 * one place this branching needs to live.
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
            $data['cq_parts'] = QuestionFormData::fillCqParts($record->cqParts);
        }

        if ($record?->question_type === QuestionType::Mcq) {
            $data['options'] = QuestionFormData::fillOptions($record->options);
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

        return QuestionFormData::cqPartRows($cqParts);
    }

    /**
     * The MCQ options Repeater's per-option `is_correct` checkbox is kept
     * as-is on each `options` entry (the form's own validation rule
     * guarantees exactly one is checked) — this just strips the UI-only
     * `has_image` flag so only `option`/`image`/`is_correct` are persisted.
     * A CQ has no explanation (only an MCQ does), so one left from before
     * the question was switched to a CQ is dropped.
     *
     * @param  array<string, mixed>  $data
     */
    protected function normalizeMcqOptions(array &$data): void
    {
        $questionType = $data['question_type'] ?? null;

        if (! in_array($questionType, [QuestionType::Mcq, QuestionType::Mcq->value], strict: true)) {
            $data['explanation'] = null;

            return;
        }

        $data['options'] = QuestionFormData::storedOptions($data['options'] ?? []);
    }

    /**
     * Saves whichever editors (Rich Text vs CKEditor) the text and the
     * explanation were written in this time as this user's preference
     * (EditorModePreference), so their next Create Question form defaults
     * to them instead of always starting on Rich Text.
     *
     * @param  array<string, mixed>  $data
     */
    protected function rememberEditorModePreference(array $data): void
    {
        EditorModePreference::rememberFromForm(Auth::user(), $data);
    }

    /**
     * Saves the topic this question was filed under as this user's
     * preference for that chapter (TopicPreference), so their next question
     * in the same chapter starts with it already selected.
     *
     * @param  array<string, mixed>  $data
     */
    protected function rememberTopicPreference(array $data): void
    {
        if (filled($data['chapter_id'] ?? null)) {
            TopicPreference::remember(Auth::user(), $data['chapter_id'], $data['topic_id'] ?? null);
        }
    }

    /**
     * "Create & create another" resets the form to its defaults; keep the
     * Class → Subject → Chapter → Topic selection so a run of questions for
     * the same topic doesn't need re-selecting each time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return Arr::only($data, ['academic_class_id', 'class_subject_id', 'chapter_id', 'topic_id']);
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
     * An edit saved as a new version (Question::editsMakeNewVersion()),
     * leaving the edited one as it is on every exam.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleEditAsNewVersion(Question $record, array $data): Model
    {
        $revision = $record->createRevisionWith($data, $this->cqPartsData);

        $notification = $record->is_private
            ? Notification::make()
                ->title(__('Saved as a new version'))
                ->body(__('Students have already sat this question, so the exams they sat keep it as it was.'))
            : Notification::make()
                ->title(__('Approved question edited'))
                ->body(__('A new pending version has been created for re-review; the previous approved version is unchanged.'));

        $notification->success()->send();

        $this->redirect(static::getResource()::getUrl('edit', ['record' => $revision]));

        return $revision;
    }
}
