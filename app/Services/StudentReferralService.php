<?php

namespace App\Services;

use App\Enums\ReferralSource;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\StudentReferral;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Whose referral a student is under, and for how long — the only place
 * those rules live. Who may refer at all, discounts and rewards are
 * ReferralService's; this class only decides when a referral starts,
 * changes and ends.
 *
 *  - A student is under one referrer at a time, for the Admin's number of
 *    days (BillingSetting::referral_lock_days) from the day it was set.
 *    After that it simply ends — nothing runs on a schedule; the next code
 *    or exam link starts a new one.
 *  - A code the student types (or the referral link they signed up
 *    through) can be swapped for another for a short while
 *    (`referral_change_days`), counted from when it was first set.
 *  - Opening a teacher's exam link sets that teacher at once and fixes it —
 *    also over a typed code still inside its change window. A referral
 *    already fixed is never replaced by an exam link.
 */
class StudentReferralService
{
    /**
     * The referral the student is under right now, with its referrer.
     */
    public function current(User $student): ?StudentReferral
    {
        return $student->studentReferrals()
            ->active()
            ->with('referrer')
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    /**
     * Whether the student may set a referrer by code now: when they have
     * none, or while the one they typed can still be swapped.
     */
    public function canChange(User $student): bool
    {
        $current = $this->current($student);

        return $current === null || $current->isChangeable();
    }

    /**
     * Puts the student under the owner of a code they typed (or of the
     * referral link they signed up through). False when their current
     * referral is already fixed.
     */
    public function startFromCode(User $student, User $referrer, ReferralSource $source = ReferralSource::Code): bool
    {
        return $this->setReferral($student, function (?StudentReferral $current) use ($referrer, $source): array {
            // A swap keeps the dates of the referral it replaces, so the
            // change window can't be stretched by swapping again and again.
            if ($current) {
                return ['referrer_id' => $referrer->id, 'source' => $source];
            }

            $settings = BillingSetting::current();

            return [
                'referrer_id' => $referrer->id,
                'source' => $source,
                'starts_at' => now(),
                'changeable_until' => $settings->referral_change_days > 0 ? now()->addDays($settings->referral_change_days) : null,
                'ends_at' => now()->addDays($settings->referral_lock_days),
            ];
        });
    }

    /**
     * Puts the student under the teacher whose exam link they opened, fixed
     * from now — unless a fixed referral is already in force.
     */
    public function startFromExam(User $student, User $teacher, Exam $exam): bool
    {
        return $this->setReferral($student, fn (): array => [
            'referrer_id' => $teacher->id,
            'source' => ReferralSource::Exam,
            'exam_id' => $exam->id,
            'starts_at' => now(),
            'changeable_until' => null,
            'ends_at' => now()->addDays(BillingSetting::current()->referral_lock_days),
        ]);
    }

    /**
     * What every way of setting a referral shares: one request at a time
     * per student (a double submit takes turns), nothing done to a referral
     * that is already fixed, the current one changed or a new one started,
     * and a log line.
     *
     * @param  Closure(?StudentReferral): array<string, mixed>  $attributesFor  The attributes to set, given the referral in force (if any).
     */
    private function setReferral(User $student, Closure $attributesFor): bool
    {
        return DB::transaction(function () use ($student, $attributesFor): bool {
            User::whereKey($student->id)->lockForUpdate()->value('id');

            $current = $this->current($student);

            if ($current && ! $current->isChangeable()) {
                return false;
            }

            $attributes = $attributesFor($current);

            $referral = $current
                ? tap($current)->update($attributes)
                : StudentReferral::create(['student_id' => $student->id, ...$attributes]);

            Log::info('Student referral set.', [
                'student_id' => $student->id,
                'referrer_id' => $referral->referrer_id,
                'student_referral_id' => $referral->id,
                'source' => $referral->source->value,
                'exam_id' => $referral->exam_id,
            ]);

            return true;
        });
    }
}
