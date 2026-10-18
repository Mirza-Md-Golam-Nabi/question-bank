<?php

namespace App\Services;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Support\EditorModePreference;
use App\Filament\Support\StimulusGroupForm;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionStimulus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saves a stimulus of the question bank with its MCQs, as one unit — the
 * one place a group is created and changed. Each MCQ is an ordinary
 * question (its status from QuestionObserver, its versions, its own marks
 * and difficulty); what is decided here is only what keeps the group whole:
 *
 *  - a group that has been approved is never changed in place: editing it
 *    makes a new version of the whole group — a new stimulus, and a new
 *    pending version of each question under it (CLAUDE.md rule 4) — while
 *    the old one stays as it is on every exam;
 *  - any other group is changed in place (QuestionPolicy refuses once a
 *    student has sat one of its questions).
 *
 * Approving or rejecting one question of a group does the whole group
 * (Question::approve(), reject()).
 */
class StimulusGroupService
{
    /**
     * The fields each MCQ of a question-bank group adds to the shared form.
     */
    public const QUESTION_FIELDS = ['difficulty'];

    /**
     * @param  array<string, mixed>  $data  The group form's state (StimulusGroupForm), with the group's `topic_id`.
     */
    public function create(Chapter $chapter, array $data): QuestionStimulus
    {
        Gate::authorize('create', Question::class);

        return DB::transaction(function () use ($chapter, $data): QuestionStimulus {
            $stimulus = QuestionStimulus::create([
                ...StimulusGroupForm::stimulus($data),
                'chapter_id' => $chapter->id,
                'created_by' => Auth::id(),
            ]);

            foreach (StimulusGroupForm::questionRows($data, self::QUESTION_FIELDS) as $row) {
                Question::create([...$row['attributes'], ...$this->shared($stimulus, $data)]);
            }

            EditorModePreference::rememberFromForm(Auth::user(), $data['editor_mode'] ?? null);

            return $stimulus;
        });
    }

    /**
     * The group's questions as they stand now — the latest version of
     * each, in order.
     *
     * @return Collection<int, Question>
     */
    public function currentQuestions(QuestionStimulus $stimulus): Collection
    {
        return $stimulus->questions()->where('is_latest', true)->get();
    }

    /**
     * @param  array<string, mixed>  $data  The group form's state, with the group's `topic_id`.
     * @return QuestionStimulus The group's stimulus now: a new one when the group was approved.
     *
     * @throws ValidationException when a question that is on an exam would be taken out of the group
     */
    public function update(QuestionStimulus $stimulus, array $data): QuestionStimulus
    {
        $questions = $this->currentQuestions($stimulus)->keyBy('id');

        foreach ($questions as $question) {
            Gate::authorize('update', $question);
        }

        $rows = StimulusGroupForm::questionRows($data, self::QUESTION_FIELDS);

        $stimulus = DB::transaction(fn (): QuestionStimulus => $questions->contains(fn (Question $question) => $question->status === QuestionStatus::Approved)
            ? $this->revise($stimulus, $questions, $rows, $data)
            : $this->changeInPlace($stimulus, $questions, $rows, $data));

        EditorModePreference::rememberFromForm(Auth::user(), $data['editor_mode'] ?? null);

        return $stimulus;
    }

    /**
     * An approved group's edit: a new stimulus and a new pending version of
     * every question, the old ones left as they are. A question left out of
     * the edit leaves the pool (it is no longer the latest), but stays on
     * the exams that already have it.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<int, array{id: int|null, attributes: array<string, mixed>}>  $rows
     * @param  array<string, mixed>  $data
     */
    private function revise(QuestionStimulus $stimulus, Collection $questions, array $rows, array $data): QuestionStimulus
    {
        $revised = QuestionStimulus::create([
            ...StimulusGroupForm::stimulus($data),
            'chapter_id' => $stimulus->chapter_id,
            'created_by' => $stimulus->created_by,
        ]);

        $shared = $this->shared($revised, $data);

        foreach ($rows as $row) {
            $question = $row['id'] ? $questions->pull($row['id']) : null;

            $question
                ? $question->createRevisionWith([...$row['attributes'], ...$shared])
                : Question::create([...$row['attributes'], ...$shared, 'status' => QuestionStatus::Pending, 'created_by' => $stimulus->created_by]);
        }

        Question::whereKey($questions->keys())->update(['is_latest' => false]);

        return $revised;
    }

    /**
     * A group not approved yet is corrected where it is. A new question
     * joins in the group's own status; one left out is removed — unless it
     * is on an exam, which it must stay on.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<int, array{id: int|null, attributes: array<string, mixed>}>  $rows
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function changeInPlace(QuestionStimulus $stimulus, Collection $questions, array $rows, array $data): QuestionStimulus
    {
        $stimulus->update(StimulusGroupForm::stimulus($data));

        $shared = $this->shared($stimulus, $data);
        $status = $questions->first()?->status ?? QuestionStatus::Pending;

        foreach ($rows as $row) {
            $question = $row['id'] ? $questions->pull($row['id']) : null;

            $question
                ? $question->update([...$row['attributes'], ...$shared])
                : Question::create([...$row['attributes'], ...$shared, 'status' => $status, 'created_by' => $stimulus->created_by]);
        }

        foreach ($questions as $removed) {
            if ($removed->isOnAnExam()) {
                throw ValidationException::withMessages(['questions' => __('A question that is on an exam cannot be taken out of its stimulus.')]);
            }

            $removed->delete();
        }

        return $stimulus;
    }

    /**
     * What every question of the group has in common.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function shared(QuestionStimulus $stimulus, array $data): array
    {
        return [
            'chapter_id' => $stimulus->chapter_id,
            'topic_id' => filled($data['topic_id'] ?? null) ? (int) $data['topic_id'] : null,
            'stimulus_id' => $stimulus->id,
            'question_type' => QuestionType::Mcq,
        ];
    }
}
