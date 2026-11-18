<?php

namespace App\Services;

use App\Enums\OmrIdentity;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Support\Collection;

/**
 * Pairs a School's exam papers with the students on its school management
 * software's list for one class (CLAUDE.md rule 12) — the one place that
 * decides whose mark is whose:
 *
 * - by the number the paper goes by: section + roll (as the entry form or
 *   the scanned OMR sheet took them), or the registration number of an OMR
 *   sheet of an exam that goes by that (`exams.omr_identity`);
 * - one paper whose name matches → `matched`; whose name doesn't →
 *   `name_mismatch`, for the School to approve (a registration number is
 *   the student's own, so a sheet without a name matches by it alone);
 * - more than one paper under the number (someone else used it) →
 *   `duplicate`, for the School to pick one;
 * - no paper → `absent` — or, when the exam goes by registration number and
 *   the student has none in the software, `no_registration`: never sent,
 *   since their sheet can't be found;
 * - papers under a number the list doesn't have → left over, never sent
 *   (the School is shown they failed).
 *
 * Only handed-in papers count.
 */
class SchoolResultMatcher
{
    public const MATCHED = 'matched';

    public const NAME_MISMATCH = 'name_mismatch';

    public const DUPLICATE = 'duplicate';

    public const ABSENT = 'absent';

    public const NO_REGISTRATION = 'no_registration';

    /**
     * @param  list<array{id: int, roll: ?string, registration?: ?string, name: ?string, section_id: ?int}>  $students  The school's list.
     * @return array{rows: list<array{student: array<string, mixed>, status: string, attempts: Collection<int, ExamAttempt>}>, unmatched: Collection<int, ExamAttempt>}
     */
    public function match(Exam $exam, int $classId, array $students): array
    {
        $byRegistration = $exam->omr_identity === OmrIdentity::Registration;

        $papers = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('school_class_id', $classId)
            ->submitted()
            ->orderBy('id')
            ->get();

        $papersByKey = $papers->groupBy(fn (ExamAttempt $paper): string => self::paperKey($paper));

        $rows = collect($students)->map(function (array $student) use ($papersByKey, $byRegistration): array {
            $mine = collect(self::studentKeys($student))
                ->flatMap(fn (string $key) => $papersByKey->get($key, collect()))
                ->unique('id')
                ->values();
            $first = $mine->first();

            return [
                'student' => $student,
                'attempts' => $mine,
                'status' => match (true) {
                    $mine->isEmpty() && $byRegistration && blank($student['registration'] ?? null) => self::NO_REGISTRATION,
                    $mine->isEmpty() => self::ABSENT,
                    $mine->count() > 1 => self::DUPLICATE,
                    $first->registration_no !== null && blank($first->guest_name) => self::MATCHED,
                    self::sameName((string) $first->guest_name, (string) ($student['name'] ?? '')) => self::MATCHED,
                    default => self::NAME_MISMATCH,
                },
            ];
        });

        $listed = collect($students)->flatMap(fn (array $student): array => self::studentKeys($student))->flip();

        return [
            'rows' => $rows->values()->all(),
            'unmatched' => $papers->reject(fn (ExamAttempt $paper): bool => $listed->has(self::paperKey($paper)))->values(),
        ];
    }

    /**
     * What a paper is found by: its registration number, or its section + roll.
     */
    private static function paperKey(ExamAttempt $paper): string
    {
        return $paper->registration_no !== null
            ? 'registration:'.ExamAttempt::normalizeRoll($paper->registration_no)
            : 'roll:'.($paper->school_section_id ?? '-').'|'.ExamAttempt::normalizeRoll((string) $paper->school_roll);
    }

    /**
     * Every key a student on the school's list can be found by.
     *
     * @param  array<string, mixed>  $student
     * @return list<string>
     */
    private static function studentKeys(array $student): array
    {
        return array_values(array_filter([
            filled($student['registration'] ?? null) ? 'registration:'.ExamAttempt::normalizeRoll((string) $student['registration']) : null,
            'roll:'.($student['section_id'] ?? '-').'|'.ExamAttempt::normalizeRoll((string) ($student['roll'] ?? '')),
        ]));
    }

    /**
     * A paper's score as the school's MCQ marks: as it is, or — when the
     * two full marks differ and the School chose to — in proportion
     * (15 of 20 → 22.5 of 30). Never above the school's full marks.
     */
    public static function marksFor(ExamAttempt $paper, Exam $exam, float $schoolFullMarks, bool $inProportion): float
    {
        $score = (float) $paper->total_score;

        if ($inProportion && $exam->scoredMarks() > 0) {
            $score = $score / $exam->scoredMarks() * $schoolFullMarks;
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
