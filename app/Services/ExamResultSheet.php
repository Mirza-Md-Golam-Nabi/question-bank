<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * The result sheet of an exam as its teacher sees it: everyone who sat it,
 * ranked by marks. The one place ranking is decided, used by the on-screen
 * results page and the printable sheet alike.
 *
 * Rules:
 *  - A participant counts once. Someone who sat the exam more than once is
 *    ranked on their FIRST submitted attempt — the same rule a guest's own
 *    result lookup follows (CLAUDE.md rule 8), so a second go at an exam
 *    whose answers they may have seen can't improve their place.
 *  - Position follows marks alone: the highest mark is position 1, and
 *    equal marks share a position (1, 2, 2, 4 — the next position skips).
 *  - Within a shared position, whoever finished faster is listed first.
 */
class ExamResultSheet
{
    /**
     * @return Collection<int, array{
     *     position: int,
     *     attempt: ExamAttempt,
     *     name: string,
     *     contact: string|null,
     *     is_guest: bool,
     *     score: float,
     *     duration_seconds: int|null,
     * }>
     */
    public function rowsFor(Exam $exam): Collection
    {
        $rows = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->submitted()
            ->with('student:id,name,email')
            ->orderBy('id')
            ->get()
            // First submitted attempt per participant.
            ->unique(fn (ExamAttempt $attempt) => $this->participantKey($attempt))
            ->map(fn (ExamAttempt $attempt): array => [
                'position' => 0,
                'attempt' => $attempt,
                'name' => $attempt->participantName(),
                'contact' => $attempt->participantContact(),
                'is_guest' => $attempt->is_guest,
                'score' => (float) $attempt->total_score,
                'duration_seconds' => $attempt->submitted_at && $attempt->started_at
                    ? max(0, (int) $attempt->started_at->diffInSeconds($attempt->submitted_at))
                    : null,
            ])
            ->sort(fn (array $a, array $b): int => [$b['score'], $a['duration_seconds'] ?? PHP_INT_MAX, $a['attempt']->id]
                <=> [$a['score'], $b['duration_seconds'] ?? PHP_INT_MAX, $b['attempt']->id])
            ->values();

        $position = 0;
        $previousScore = null;

        return $rows->map(function (array $row, int $index) use (&$position, &$previousScore): array {
            // Equal marks keep the position of the first of them.
            if ($row['score'] !== $previousScore) {
                $position = $index + 1;
                $previousScore = $row['score'];
            }

            return [...$row, 'position' => $position];
        });
    }

    /**
     * How the participants did on each question — how many got it right,
     * how many got it wrong, how many left it blank — with the question
     * most often answered wrongly first, so the teacher sees straight away
     * what the class struggled with.
     *
     * Counted over the same participants as the result sheet (one attempt
     * per person), and only over the questions shown online: those are the
     * ones that are auto-graded.
     *
     * @return Collection<int, array{
     *     question: Question,
     *     correct: int,
     *     wrong: int,
     *     unanswered: int,
     *     participants: int,
     * }>
     */
    public function questionStatsFor(Exam $exam): Collection
    {
        $attemptIds = $this->rowsFor($exam)->map(fn (array $row) => $row['attempt']->id);
        $participants = $attemptIds->count();

        $tallies = $participants === 0
            ? collect()
            : AttemptAnswer::query()
                ->whereIn('attempt_id', $attemptIds)
                ->whereNotNull('is_correct')
                ->selectRaw('question_id, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count, COUNT(*) AS answered_count')
                ->groupBy('question_id')
                ->get()
                ->keyBy('question_id');

        return $exam->onlineQuestions
            ->load('cqParts')
            ->values()
            ->map(function (Question $question, int $index) use ($tallies, $participants): array {
                $correct = (int) ($tallies->get($question->id)?->correct_count ?? 0);
                $answered = (int) ($tallies->get($question->id)?->answered_count ?? 0);

                return [
                    'question' => $question,
                    'correct' => $correct,
                    'wrong' => $answered - $correct,
                    'unanswered' => $participants - $answered,
                    'participants' => $participants,
                    'paper_order' => $index,
                ];
            })
            // Most wrong answers first; then most left blank; then paper order.
            ->sort(fn (array $a, array $b): int => [$b['wrong'], $b['unanswered'], $a['paper_order']]
                <=> [$a['wrong'], $a['unanswered'], $b['paper_order']])
            ->values();
    }

    /**
     * The participants who answered one question wrongly, with what each
     * of them chose — fetched for that single question only, when the
     * teacher asks for it, rather than for every question up front.
     *
     * Empty for a question that isn't part of the exam.
     *
     * @return Collection<int, array{position: int, name: string, contact: string|null, answer: string|null}>
     */
    public function wrongAnswersFor(Exam $exam, int $questionId): Collection
    {
        if (! $exam->onlineQuestions->contains('id', $questionId)) {
            return collect();
        }

        $rows = $this->rowsFor($exam)->keyBy(fn (array $row) => $row['attempt']->id);

        return AttemptAnswer::query()
            ->where('question_id', $questionId)
            ->where('is_correct', false)
            ->whereIn('attempt_id', $rows->keys())
            ->get(['attempt_id', 'student_answer'])
            ->map(fn (AttemptAnswer $answer): array => [
                'position' => $rows[$answer->attempt_id]['position'],
                'name' => $rows[$answer->attempt_id]['name'],
                'contact' => $rows[$answer->attempt_id]['contact'],
                'answer' => $answer->student_answer,
            ])
            ->sortBy('position')
            ->values();
    }

    /**
     * The participants who left one question blank — the ones behind its
     * "not answered" count, i.e. everyone on the result sheet who has no
     * graded answer to it. Fetched for that single question only, like
     * wrongAnswersFor().
     *
     * Empty for a question that isn't part of the exam.
     *
     * @return Collection<int, array{position: int, name: string, contact: string|null}>
     */
    public function unansweredBy(Exam $exam, int $questionId): Collection
    {
        if (! $exam->onlineQuestions->contains('id', $questionId)) {
            return collect();
        }

        $rows = $this->rowsFor($exam);

        $answeredAttemptIds = AttemptAnswer::query()
            ->where('question_id', $questionId)
            ->whereNotNull('is_correct')
            ->whereIn('attempt_id', $rows->map(fn (array $row) => $row['attempt']->id))
            ->pluck('attempt_id');

        return $rows
            ->reject(fn (array $row): bool => $answeredAttemptIds->contains($row['attempt']->id))
            ->map(fn (array $row): array => [
                'position' => $row['position'],
                'name' => $row['name'],
                'contact' => $row['contact'],
            ])
            ->values();
    }

    /**
     * One participant's attempt, ready to show answer by answer — only if
     * it really belongs to this exam.
     */
    public function attemptFor(Exam $exam, int|string $attemptId): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->submitted()
            ->with(['student:id,name,email', 'answers'])
            ->find($attemptId)
            ?->setRelation('exam', $exam);
    }

    /**
     * How many people have started the exam but not handed it in yet.
     */
    public function pendingCountFor(Exam $exam): int
    {
        return ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->count();
    }

    /**
     * What makes two attempts "the same person": a logged-in student's
     * account, or a guest's name + phone/email.
     */
    private function participantKey(ExamAttempt $attempt): string
    {
        if (! $attempt->is_guest) {
            return 'student:'.$attempt->student_id;
        }

        return 'guest:'.ExamAttempt::normalizeGuestContact((string) $attempt->guest_contact)
            .'|'.ExamAttempt::normalizeGuestName((string) $attempt->guest_name);
    }

    /**
     * "4m 05s" — how long an attempt took, for the sheet.
     */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60);
    }
}
