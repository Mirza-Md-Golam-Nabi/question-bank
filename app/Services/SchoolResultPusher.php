<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\SchoolProfile;
use App\Models\SchoolResultPush;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends a School exam's MCQ marks to the school management software's
 * results for one of its exams and subjects, and keeps a record of it
 * (SchoolResultPush). What the software may change — never a published
 * result, never written/practical marks, marks already there only when
 * told to — is the software's own rule; what it left as it was comes back
 * and is kept with the record.
 */
class SchoolResultPusher
{
    public function __construct(private readonly SchoolInstallationClient $client) {}

    /**
     * @param  list<array{student_id: int, mcq_marks: float|null, absent: bool}>  $marks
     * @param  array{class_id: int, class_name: ?string, exam_id: int, exam_name: ?string, subject_id: int, subject_name: ?string, full_marks: float}  $target
     *
     * @throws RuntimeException when the software refuses or can't be reached — nothing is recorded then
     */
    public function push(Exam $exam, User $school, array $target, array $marks, bool $inProportion, bool $overwrite): SchoolResultPush
    {
        $answer = $this->client->post(
            SchoolProfile::for($school),
            "/api/question-bank/exams/{$target['exam_id']}/subjects/{$target['subject_id']}/results",
            ['overwrite' => $overwrite, 'marks' => $marks],
        );

        $push = SchoolResultPush::query()->create([
            'exam_id' => $exam->id,
            'sent_by' => $school->id,
            'school_class_id' => $target['class_id'],
            'school_class_name' => $target['class_name'],
            'school_exam_id' => $target['exam_id'],
            'school_exam_name' => $target['exam_name'],
            'school_subject_id' => $target['subject_id'],
            'school_subject_name' => $target['subject_name'],
            'school_full_marks' => $target['full_marks'],
            'in_proportion' => $inProportion,
            'overwrite' => $overwrite,
            'sent_count' => count($marks),
            'saved_count' => (int) ($answer['saved'] ?? 0),
            'skipped' => array_values((array) ($answer['skipped'] ?? [])),
        ]);

        Log::info('School exam marks sent to the school management software.', [
            'push_id' => $push->id,
            'exam_id' => $exam->id,
            'school_id' => $school->id,
            'sent' => $push->sent_count,
            'saved' => $push->saved_count,
        ]);

        return $push;
    }
}
