<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDeliveryMode;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Support\OmrSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns one OMR sheet the teacher scanned (CLAUDE.md rule 10.11) into a
 * graded paper on the exam — the one place that does. It is an ordinary
 * attempt from then on, marked by ExamAttempt::submitAndAutoGrade() like any
 * other (negative marking, past papers' several or no correct answers…),
 * ranked on the result sheet and counted in the questions' difficulty.
 *
 * Who sat it is the roll filled in on the sheet (with a School's exam, in
 * the class and section the School scanned for) and, if the teacher typed
 * one, a name — there is no account to tie it to, so it counts like a
 * guest's paper. A roll is saved once per exam: scanning it again is
 * refused rather than overwriting a paper already marked and counted.
 */
class OmrResultRecorder
{
    /**
     * @param  array<string, int|string|null>  $marks  question key => option index (0 = the first), "several" or null — as read off the sheet and checked by the teacher.
     * @param  array{class: array{id: int, name: string}, section: array{id: int, name: string}|null}|null  $school  The class and section of a School's exam.
     *
     * @throws ValidationException when the roll is missing or already saved, or the exam is full
     */
    public function record(Exam $exam, string $roll, ?string $name, array $marks, ?array $school = null): ExamAttempt
    {
        $roll = ExamAttempt::normalizeRoll($roll);

        if ($roll === '') {
            throw ValidationException::withMessages(['roll' => __('Write the roll number.')]);
        }

        return DB::transaction(function () use ($exam, $roll, $name, $marks, $school): ExamAttempt {
            // Two phones saving the same roll at once take turns.
            Exam::whereKey($exam->id)->lockForUpdate()->value('id');

            $saved = ExamAttempt::query()
                ->where('exam_id', $exam->id)
                ->where('is_omr', true)
                ->where('school_class_id', $school['class']['id'] ?? null)
                ->where('school_section_id', $school['section']['id'] ?? null)
                ->where('school_roll', $roll)
                ->first();

            if ($saved) {
                throw ValidationException::withMessages([
                    'roll' => __('Roll :roll is already saved for this exam (:marks marks).', ['roll' => $roll, 'marks' => (float) $saved->total_score]),
                ]);
            }

            if ($exam->isFull()) {
                throw ValidationException::withMessages([
                    'roll' => __('This exam is full: as many people as it allows have already sat it.'),
                ]);
            }

            // A print-only paper made before its MCQ marks were kept apart.
            if ($exam->delivery_mode === ExamDeliveryMode::Offline && $exam->mcq_marks === null) {
                $exam->recalculateTotalMarks();
            }

            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => null,
                'is_guest' => true,
                'is_omr' => true,
                'guest_name' => filled($name) ? trim($name) : null,
                'school_class_id' => $school['class']['id'] ?? null,
                'school_class_name' => $school['class']['name'] ?? null,
                'school_section_id' => $school['section']['id'] ?? null,
                'school_section_name' => $school['section']['name'] ?? null,
                'school_roll' => $roll,
                'started_at' => now(),
                'status' => ExamAttemptStatus::InProgress,
            ])->setRelation('exam', $exam);

            $attempt->recordAnswers(OmrSheet::for($exam)->answersFrom($marks));
            $attempt->submitAndAutoGrade();

            return $attempt;
        });
    }
}
