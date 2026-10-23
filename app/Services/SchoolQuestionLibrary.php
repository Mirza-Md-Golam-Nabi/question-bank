<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What a School's students may read of its own questions in the school
 * management software (CLAUDE.md rule 12): only questions already open to
 * them (Question::releasedToSchoolStudents()), of any class, browsed by
 * class and subject, then chapter — with how many there are of each.
 */
class SchoolQuestionLibrary
{
    /**
     * How many questions one page of the list shows.
     */
    public const PER_PAGE = 20;

    /**
     * The classes' subjects that have open questions, as "Class 6 · Math"
     * with the count, in class and subject order.
     *
     * @return Collection<int, array{id: int, label: string, count: int}>
     */
    public function classSubjects(User $school): Collection
    {
        $counts = $this->released($school)
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->groupBy('chapters.class_subject_id')
            ->selectRaw('chapters.class_subject_id, count(*) as questions_count')
            ->toBase()
            ->pluck('questions_count', 'class_subject_id');

        return ClassSubject::query()
            ->with(['academicClass', 'subject'])
            ->findMany($counts->keys())
            ->sortBy([fn (ClassSubject $a, ClassSubject $b): int => [$a->academicClass?->order_index, $a->subject?->name] <=> [$b->academicClass?->order_index, $b->subject?->name]])
            ->map(fn (ClassSubject $classSubject): array => [
                'id' => $classSubject->id,
                'label' => $classSubject->label(),
                'count' => (int) $counts[$classSubject->id],
            ])
            ->values();
    }

    /**
     * The chapters of one class's subject that have open questions, in
     * chapter order, with the count.
     *
     * @return Collection<int, array{id: int, name: string, count: int}>
     */
    public function chapters(User $school, int $classSubjectId): Collection
    {
        $counts = $this->released($school)
            ->whereHas('chapter', fn (Builder $query) => $query->where('class_subject_id', $classSubjectId))
            ->groupBy('questions.chapter_id')
            ->selectRaw('questions.chapter_id, count(*) as questions_count')
            ->toBase()
            ->pluck('questions_count', 'chapter_id');

        return Chapter::query()
            ->whereKey($counts->keys())
            ->orderBy('order_index')
            ->get()
            ->map(fn (Chapter $chapter): array => [
                'id' => $chapter->id,
                'name' => $chapter->name,
                'count' => (int) $counts[$chapter->id],
            ]);
    }

    /**
     * One page of the open questions of a class's subject — or of one of
     * its chapters — in chapter order, a shared stimulus's questions
     * together.
     *
     * @return LengthAwarePaginator<int, Question>
     */
    public function questions(User $school, int $classSubjectId, ?int $chapterId): LengthAwarePaginator
    {
        return $this->released($school)
            ->select('questions.*')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->where('chapters.class_subject_id', $classSubjectId)
            ->when($chapterId, fn (Builder $query) => $query->where('questions.chapter_id', $chapterId))
            ->with(['cqParts', 'stimulus'])
            ->orderBy('chapters.order_index')
            ->orderByRaw('questions.question_type = ? desc', [QuestionType::Mcq->value])
            ->orderBy('questions.stimulus_id')
            ->orderBy('questions.stimulus_order')
            ->orderBy('questions.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return Builder<Question>
     */
    private function released(User $school): Builder
    {
        return Question::query()->releasedToSchoolStudents($school);
    }
}
