<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Support\Collection;

/**
 * Pairs a School's exam papers with the students on its school management
 * software's list for one class (CLAUDE.md rule 12) — the one place that
 * decides whose mark is whose:
 *
 * - by section + roll (the papers' own, as the entry form took them);
 * - one paper whose name matches → `matched`; whose name doesn't →
 *   `name_mismatch`, for the School to approve;
 * - more than one paper under the roll (someone else used it) → `duplicate`,
 *   for the School to pick one;
 * - no paper → `absent`;
 * - papers under a roll the list doesn't have → left over, never sent.
 *
 * Only handed-in papers count.
 */
class SchoolResultMatcher
{
    public const MATCHED = 'matched';

    public const NAME_MISMATCH = 'name_mismatch';

    public const DUPLICATE = 'duplicate';

    public const ABSENT = 'absent';

    /**
     * @param  list<array{id: int, roll: ?string, name: ?string, section_id: ?int}>  $students  The school's list.
     * @return array{rows: list<array{student: array<string, mixed>, status: string, attempts: Collection<int, ExamAttempt>}>, unmatched: Collection<int, ExamAttempt>}
     */
    public function match(Exam $exam, int $classId, array $students): array
    {
        $papers = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('school_class_id', $classId)
            ->submitted()
            ->orderBy('id')
            ->get();

        $key = fn (?int $sectionId, ?string $roll): string => ($sectionId ?? '-').'|'.ExamAttempt::normalizeRoll((string) $roll);
        $papersByRoll = $papers->groupBy(fn (ExamAttempt $paper): string => $key($paper->school_section_id, $paper->school_roll));

        $rows = collect($students)->map(function (array $student) use ($papersByRoll, $key): array {
            $mine = $papersByRoll->get($key($student['section_id'] ?? null, $student['roll'] ?? null), collect())->values();

            return [
                'student' => $student,
                'attempts' => $mine,
                'status' => match (true) {
                    $mine->isEmpty() => self::ABSENT,
                    $mine->count() > 1 => self::DUPLICATE,
                    self::sameName((string) $mine->first()->guest_name, (string) ($student['name'] ?? '')) => self::MATCHED,
                    default => self::NAME_MISMATCH,
                },
            ];
        });

        $listed = collect($students)->map(fn (array $student): string => $key($student['section_id'] ?? null, $student['roll'] ?? null))->flip();

        return [
            'rows' => $rows->values()->all(),
            'unmatched' => $papers->reject(fn (ExamAttempt $paper): bool => $listed->has($key($paper->school_section_id, $paper->school_roll)))->values(),
        ];
    }

    /**
     * A paper's score as the school's MCQ marks: as it is, or — when the
     * two full marks differ and the School chose to — in proportion
     * (15 of 20 → 22.5 of 30). Never above the school's full marks.
     */
    public static function marksFor(ExamAttempt $paper, Exam $exam, float $schoolFullMarks, bool $inProportion): float
    {
        $score = (float) $paper->total_score;

        if ($inProportion && (float) $exam->total_marks > 0) {
            $score = $score / (float) $exam->total_marks * $schoolFullMarks;
        }

        return round(min($score, $schoolFullMarks), 2);
    }

    /**
     * Whether two spellings are the same name: case, dots and spaces don't
     * matter, nor do the usual prefixes ("Md.", "Mohammad", "মোঃ", "মোছাঃ"…).
     */
    public static function sameName(string $one, string $other): bool
    {
        return self::nameKey($one) !== '' && self::nameKey($one) === self::nameKey($other);
    }

    private static function nameKey(string $name): string
    {
        $prefixes = ['md', 'mohammad', 'mohammed', 'muhammad', 'mohd', 'mst', 'mosammat', 'mosammad', 'মোঃ', 'মো', 'মোহাম্মদ', 'মুহাম্মদ', 'মোছাঃ', 'মোসাম্মৎ', 'মোছা'];

        $words = preg_split('/\s+/u', trim(mb_strtolower(preg_replace('/[.,:;\-_()]+/u', ' ', $name))), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_values(array_diff($words, $prefixes)));
    }
}
