<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\BoardMcqQuestion;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A student's mistakes list: the MCQs they got wrong, kept until they have
 * answered each correctly a number of times in a row (the Admin's number —
 * BillingSetting::mistake_clear_streak). The one place that list is
 * written and read.
 *
 * Every paper a logged-in student hands in feeds it, whatever kind of exam
 * it was — a teacher's, their own practice, a past paper (CLAUDE.md rule
 * 5.4): a wrong answer (or a blank) puts the question on the list, counts
 * one more time wrong, keeps what was picked and starts its run of correct
 * answers again; a correct answer adds to the run, and a long enough run
 * takes the question off. A past paper's MCQ without a correct answer is
 * never wrong, so never on it.
 *
 * A question-bank mistake is recorded against the question's first
 * version, so a question corrected since is still the same mistake,
 * practised in its current wording. A past paper's MCQ has no versions.
 *
 * One thing must never happen: a mistake made on a teacher's exam being
 * practised — answer shown — while that exam's answers are still hidden.
 * So such a mistake stays locked until the exam releases its answers.
 *
 * The list is shown, and practised, by section: each subject of the
 * student's class (a board or school test paper of that subject counts
 * in it), and one more for the past papers outside them — admission and
 * job papers, which have no subject, and papers of other classes. A
 * section narrows further by MistakeFilter: the question bank's mistakes
 * by chapter and topic, the past papers' by year.
 */
class StudentMistakeTracker
{
    /**
     * Brings the list up to date with a just-graded attempt. A handful of
     * queries whatever the length of the paper.
     *
     * @param  array<int, int>  $correctQuestionIds  The question-bank questions this attempt answered correctly.
     * @param  array<int, int>  $correctPaperQuestionIds  The past paper MCQs it answered correctly.
     */
    public function recordAttempt(ExamAttempt $attempt, array $correctQuestionIds, array $correctPaperQuestionIds = []): void
    {
        if (! $attempt->student_id) {
            return;
        }

        $exam = $attempt->exam;
        $correctKeys = collect($correctQuestionIds)->map(fn (int $id): string => (string) $id)
            ->concat(collect($correctPaperQuestionIds)->map(fn (int $id): string => AttemptAnswer::PAPER_KEY_PREFIX.$id))
            ->flip();

        // Only what can be right or wrong: MCQs with a correct answer.
        [$right, $wrong] = $exam->onlineItems()
            ->filter(fn (Model $question): bool => $question->question_type === QuestionType::Mcq && $question->hasCorrectAnswer())
            ->partition(fn (Model $question): bool => $correctKeys->has(AttemptAnswer::keyFor($question)));

        $this->recordWrong($attempt, $exam, $wrong);
        $this->recordRight($attempt->student_id, $right);
    }

    /**
     * Replays every handed-in paper of a logged-in student, oldest first,
     * into the list — how the list is built from scratch.
     */
    public function rebuildFromSubmittedAttempts(): void
    {
        ExamAttempt::query()
            ->submitted()
            ->whereNotNull('student_id')
            ->with(ExamAttempt::withCorrectAnswers())
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get()
            ->each(function (ExamAttempt $attempt): void {
                if ($attempt->exam) {
                    $this->recordAttempt($attempt, ...$attempt->correctlyAnsweredIds());
                }
            });
    }

    /**
     * @param  Collection<int, Model>  $wrong
     */
    private function recordWrong(ExamAttempt $attempt, Exam $exam, Collection $wrong): void
    {
        if ($wrong->isEmpty()) {
            return;
        }

        $locksUntilAnswersAreOut = ! $exam->showsAnswersToStudents();
        $picks = $attempt->savedAnswers();
        $when = $attempt->submitted_at ?? now();

        $wrong
            ->groupBy(fn (Model $question): string => AttemptAnswer::columnFor($question))
            ->each(function (Collection $questions, string $column) use ($attempt, $exam, $locksUntilAnswersAreOut, $picks, $when): void {
                $rows = $questions
                    ->mapWithKeys(fn (Model $question): array => [self::mistakeIdOf($question) => [
                        'student_id' => $attempt->student_id,
                        $column => self::mistakeIdOf($question),
                        'correct_streak' => 0,
                        'wrong_count' => 1,
                        'last_answer' => $picks[AttemptAnswer::keyFor($question)] ?? null,
                        'last_wrong_exam_id' => $exam->id,
                        'last_wrong_at' => $when,
                        'locked_by_exam_id' => $locksUntilAnswersAreOut ? $exam->id : null,
                        'created_at' => $when,
                        'updated_at' => $when,
                    ]])
                    ->values();

                DB::table('student_question_mistakes')->upsert(
                    $rows->all(),
                    ['student_id', $column],
                    [
                        'correct_streak', 'last_answer', 'last_wrong_exam_id', 'last_wrong_at', 'updated_at',
                        'wrong_count' => DB::raw('wrong_count + 1'),
                        // A mistake already locked by another exam stays
                        // locked by it unless this exam locks it too.
                        ...($locksUntilAnswersAreOut ? ['locked_by_exam_id'] : []),
                    ],
                );
            });
    }

    /**
     * @param  Collection<int, Model>  $right
     */
    private function recordRight(int $studentId, Collection $right): void
    {
        $right
            ->groupBy(fn (Model $question): string => AttemptAnswer::columnFor($question))
            ->each(function (Collection $questions, string $column) use ($studentId): void {
                $onTheList = fn (): QueryBuilder => DB::table('student_question_mistakes')
                    ->where('student_id', $studentId)
                    ->whereIn($column, $questions->map(fn (Model $question): int => self::mistakeIdOf($question))->unique()->values());

                $onTheList()->update(['correct_streak' => DB::raw('correct_streak + 1'), 'updated_at' => now()]);
                $onTheList()->where('correct_streak', '>=', BillingSetting::current()->mistake_clear_streak)->delete();
            });
    }

    /**
     * The questions a student can practise their mistakes on right now:
     * the current approved version of each question-bank mistake that
     * isn't locked. Self-practice shows the answer, so only approved
     * questions qualify — a teacher's own pending question waits until it
     * is approved.
     *
     * Given a question query of the caller's own (a chapter's questions,
     * say), it narrows that one instead of starting a new one.
     *
     * @param  Builder<Question>|null  $questions
     * @return Builder<Question>
     */
    public function practisableQuestions(User $student, ?Builder $questions = null): Builder
    {
        return ($questions ?? Question::query())
            ->approvedPool()
            ->where('questions.question_type', QuestionType::Mcq)
            ->whereExists(fn (QueryBuilder $mistake) => $this->unlocked($mistake, $student)
                ->whereRaw('mistake.question_id = coalesce(questions.parent_id, questions.id)'));
    }

    /**
     * The past paper MCQs a student can practise their mistakes on right
     * now: of an approved paper, with a correct answer, not locked — and,
     * given a section (a subject of the student's class, or null for the
     * past papers outside them), only that section's.
     *
     * @return Builder<BoardMcqQuestion>
     */
    public function practisablePaperQuestions(User $student, ClassSubject|false|null $section = false): Builder
    {
        return BoardMcqQuestion::query()
            ->where('board_mcq_questions.has_correct_answer', true)
            ->whereHas('boardQuestionPaper', fn (Builder $paper) => $paper
                ->where('status', QuestionStatus::Approved)
                ->when($section !== false, fn (Builder $paper) => $section
                    ? $paper->where('class_subject_id', $section->id)
                    : $paper->where(fn (Builder $outside) => $outside
                        ->whereNull('class_subject_id')
                        ->orWhereNotIn('class_subject_id', $this->classSubjectIdsOf($student)))))
            ->whereExists(fn (QueryBuilder $mistake) => $this->unlocked($mistake, $student)
                ->whereColumn('mistake.board_mcq_question_id', 'board_mcq_questions.id'));
    }

    /**
     * The student's rows of the list that may be practised — not locked by
     * a teacher's exam whose answers are still hidden.
     */
    private function unlocked(QueryBuilder $mistake, User $student): QueryBuilder
    {
        return $mistake
            ->from('student_question_mistakes as mistake')
            ->where('mistake.student_id', $student->id)
            ->where(fn (QueryBuilder $unlocked) => $unlocked
                ->whereNull('mistake.locked_by_exam_id')
                ->orWhereExists(fn (QueryBuilder $exam) => $exam
                    ->from('exams')
                    ->whereColumn('exams.id', 'mistake.locked_by_exam_id')
                    ->where(fn (QueryBuilder $released) => Exam::whereShowsAnswersToStudents($released))));
    }

    /**
     * The questions a student has had on a paper that fed their mistakes
     * list (answered or left blank — a blank is a mistake too): what their
     * mistakes in a chapter are a share of. Read like practisableQuestions():
     * the current approved MCQ of each, matched across versions.
     *
     * @param  Builder<Question>|null  $questions
     * @return Builder<Question>
     */
    public function questionsSat(User $student, ?Builder $questions = null): Builder
    {
        return ($questions ?? Question::query())
            ->approvedPool()
            ->where('questions.question_type', QuestionType::Mcq)
            ->whereExists(fn (QueryBuilder $sat) => $sat
                ->from('exam_attempts as attempt')
                ->join('exam_questions as on_paper', 'on_paper.exam_id', '=', 'attempt.exam_id')
                ->join('questions as asked', 'asked.id', '=', 'on_paper.question_id')
                ->where('attempt.student_id', $student->id)
                ->whereNotNull('attempt.statistics_recorded_at')
                ->whereRaw('coalesce(asked.parent_id, asked.id) = coalesce(questions.parent_id, questions.id)'));
    }

    /**
     * How large a share of the questions sat the mistakes are, in whole
     * percent: 3 mistakes of 5 questions is 60.
     */
    public static function mistakePercent(int $mistakes, int $sat): int
    {
        return $sat > 0 ? (int) min(100, round($mistakes / $sat * 100)) : 0;
    }

    /**
     * How many practisable mistakes the student has in each section of
     * their class — what the practice page offers to choose from: each
     * subject (by class_subject id) where there are any, and the past
     * papers outside them (keyed null) when there are any.
     *
     * @return Collection<int|string, int> class_subject id (or '' for the past papers outside them) => mistakes
     */
    public function countsBySection(User $student): Collection
    {
        if (! $student->academic_class_id) {
            return collect();
        }

        $inClass = $this->classSubjectIdsOf($student);

        $bank = $this->practisableQuestions($student)
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->whereIn('chapters.class_subject_id', $inClass)
            ->groupBy('chapters.class_subject_id')
            ->toBase()
            ->selectRaw('chapters.class_subject_id as section, count(*) as mistakes')
            ->pluck('mistakes', 'section');

        $papers = $this->practisablePaperQuestions($student)
            ->join('board_question_papers', 'board_question_papers.id', '=', 'board_mcq_questions.board_question_paper_id')
            ->groupBy('board_question_papers.class_subject_id')
            ->toBase()
            ->selectRaw('board_question_papers.class_subject_id as section, count(*) as mistakes')
            ->pluck('mistakes', 'section');

        $counts = [];

        foreach ($bank as $section => $mistakes) {
            $counts[(int) $section] = (int) $mistakes;
        }

        foreach ($papers as $section => $mistakes) {
            $key = $inClass->contains((int) $section) ? (int) $section : '';
            $counts[$key] = ($counts[$key] ?? 0) + (int) $mistakes;
        }

        return collect($counts);
    }

    /**
     * How many practisable question-bank mistakes the student has in each
     * chapter of a subject of their class — what the page offers to narrow
     * the subject to.
     *
     * @return Collection<int, int> chapter id => mistakes
     */
    public function countsByChapter(User $student, ClassSubject $section): Collection
    {
        return $this->bankMistakesIn($student, $section, new MistakeFilter)
            ->toBase()
            ->groupBy('questions.chapter_id')
            ->selectRaw('questions.chapter_id as chapter, count(*) as mistakes')
            ->pluck('mistakes', 'chapter')
            ->mapWithKeys(fn (int|string $mistakes, int|string $chapter): array => [(int) $chapter => (int) $mistakes]);
    }

    /**
     * How many of those in one chapter are in each of its topics (a
     * question without a topic is in none).
     *
     * @return Collection<int, int> topic id => mistakes
     */
    public function countsByTopic(User $student, ClassSubject $section, int $chapterId): Collection
    {
        return $this->bankMistakesIn($student, $section, new MistakeFilter(chapterId: $chapterId))
            ->whereNotNull('questions.topic_id')
            ->toBase()
            ->groupBy('questions.topic_id')
            ->selectRaw('questions.topic_id as topic, count(*) as mistakes')
            ->pluck('mistakes', 'topic')
            ->mapWithKeys(fn (int|string $mistakes, int|string $topic): array => [(int) $topic => (int) $mistakes]);
    }

    /**
     * How many practisable past paper mistakes the student has from each
     * year in one section (a subject of their class, or null for the past
     * papers outside them), the latest year first.
     *
     * @return Collection<int, int> year => mistakes
     */
    public function countsByYear(User $student, ?ClassSubject $section): Collection
    {
        return $this->paperMistakesIn($student, $section, new MistakeFilter)
            ->join('board_question_papers', 'board_question_papers.id', '=', 'board_mcq_questions.board_question_paper_id')
            ->toBase()
            ->groupBy('board_question_papers.year')
            ->orderByDesc('board_question_papers.year')
            ->selectRaw('board_question_papers.year as year, count(*) as mistakes')
            ->pluck('mistakes', 'year')
            ->mapWithKeys(fn (int|string $mistakes, int|string $year): array => [(int) $year => (int) $mistakes]);
    }

    /**
     * Up to `$count` of the student's practisable mistakes in one section,
     * as far as the filter narrows it, drawn at random from both kinds —
     * the question bank's only of the given difficulties, when the
     * student's plan limits them (a past paper's MCQ has no difficulty).
     *
     * @param  array<int, Difficulty>|null  $difficulties
     * @return Collection<int, Question|BoardMcqQuestion>
     */
    public function drawForPractice(User $student, ?ClassSubject $section, int $count, ?array $difficulties = null, MistakeFilter $filter = new MistakeFilter): Collection
    {
        // Drawn over ids first, as the auto-generated practice exam does.
        $questionIds = $section && $filter->includesQuestionBank()
            ? $this->bankMistakesIn($student, $section, $filter)
                ->when($difficulties !== null, fn (Builder $query) => $query->whereIn('questions.difficulty', $difficulties))
                ->pluck('questions.id')
                ->map(fn (int $id): array => [Question::class, $id])
            : collect();

        $paperQuestionIds = $filter->includesPastPapers()
            ? $this->paperMistakesIn($student, $section, $filter)
                ->pluck('board_mcq_questions.id')
                ->map(fn (int $id): array => [BoardMcqQuestion::class, $id])
            : collect();

        $drawn = $questionIds->concat($paperQuestionIds);
        $drawn = $drawn->isEmpty() ? collect() : collect($drawn->random(min($count, $drawn->count())));

        return $drawn
            ->groupBy(fn (array $pick): string => $pick[0])
            ->flatMap(fn (Collection $picks, string $model) => $model::query()->whereKey($picks->pluck(1))->get())
            ->shuffle()
            ->values();
    }

    /**
     * The student's practisable mistakes in one section, as far as the
     * filter narrows it, the latest first, a page at a time — each as the
     * question to show (the current version
     * of a question-bank one), what was picked the last time it was got
     * wrong (null for a blank; matched against the current options by
     * their text, as an exam's answers are), how often it was got wrong,
     * and when and on which exam last.
     *
     * @return LengthAwarePaginator<int, array{question: Question|BoardMcqQuestion, last_answer: ?string, wrong_count: int, last_wrong_at: ?string, exam_title: ?string}>
     */
    public function listFor(User $student, ?ClassSubject $section, MistakeFilter $filter = new MistakeFilter, int $perPage = 10): LengthAwarePaginator
    {
        $page = DB::table('student_question_mistakes')
            ->where('student_id', $student->id)
            ->where(function (QueryBuilder $either) use ($student, $section, $filter): void {
                // Nothing at all unless one of the kinds below is let in.
                $either->whereRaw('1 = 0');

                if ($section && $filter->includesQuestionBank()) {
                    $either->orWhereIn('question_id', $this->bankMistakesIn($student, $section, $filter)
                        ->toBase()
                        ->select(DB::raw('coalesce(questions.parent_id, questions.id)')));
                }

                if ($filter->includesPastPapers()) {
                    $either->orWhereIn('board_mcq_question_id', $this->paperMistakesIn($student, $section, $filter)
                        ->toBase()
                        ->select('board_mcq_questions.id'));
                }
            })
            ->orderByDesc('last_wrong_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($page->items());

        $questions = Question::approvedPool()
            ->with('stimulus')
            ->whereIn(DB::raw('coalesce(parent_id, id)'), $rows->pluck('question_id')->filter()->values())
            ->get()
            ->keyBy(fn (Question $question): int => self::firstVersionId($question));

        $paperQuestions = BoardMcqQuestion::query()
            ->with(['stimulus', 'boardQuestionPaper.board'])
            ->whereKey($rows->pluck('board_mcq_question_id')->filter()->values())
            ->get()
            ->keyBy('id');

        $examTitles = Exam::query()
            ->whereKey($rows->pluck('last_wrong_exam_id')->filter()->unique()->values())
            ->pluck('title', 'id');

        return $page->through(fn (object $row): array => [
            'question' => $row->question_id ? $questions->get($row->question_id) : $paperQuestions->get($row->board_mcq_question_id),
            'last_answer' => $row->last_answer,
            'wrong_count' => (int) $row->wrong_count,
            'last_wrong_at' => $row->last_wrong_at,
            'exam_title' => $examTitles->get($row->last_wrong_exam_id),
        ]);
    }

    /**
     * The practisable question-bank mistakes of one subject of the
     * student's class, narrowed to the filter's chapter and topic.
     *
     * @return Builder<Question>
     */
    private function bankMistakesIn(User $student, ClassSubject $section, MistakeFilter $filter): Builder
    {
        return $this->practisableQuestions($student)
            ->ofClassSubject($section->id)
            ->when($filter->chapterId, fn (Builder $query, int $chapterId) => $query->where('questions.chapter_id', $chapterId))
            ->when($filter->topicId, fn (Builder $query, int $topicId) => $query->where('questions.topic_id', $topicId));
    }

    /**
     * The practisable past paper mistakes of one section, narrowed to the
     * filter's year.
     *
     * @return Builder<BoardMcqQuestion>
     */
    private function paperMistakesIn(User $student, ?ClassSubject $section, MistakeFilter $filter): Builder
    {
        return $this->practisablePaperQuestions($student, $section)
            ->when($filter->year, fn (Builder $query, int $year) => $query
                ->whereHas('boardQuestionPaper', fn (Builder $paper) => $paper->where('year', $year)));
    }

    /**
     * The id every version of a question shares: that of its first version.
     */
    public static function firstVersionId(Question $question): int
    {
        return $question->parent_id ?? $question->id;
    }

    /**
     * The id a question is on the list by: a question-bank question's first
     * version, or a past paper's MCQ itself.
     */
    private static function mistakeIdOf(Model $question): int
    {
        return $question instanceof Question ? self::firstVersionId($question) : $question->getKey();
    }

    /**
     * The class_subject ids of the student's own class.
     *
     * @return Collection<int, int>
     */
    private function classSubjectIdsOf(User $student): Collection
    {
        return once(fn (): Collection => ClassSubject::query()
            ->where('academic_class_id', $student->academic_class_id)
            ->pluck('id'));
    }
}
