<?php

use App\Enums\ReferralSource;
use App\Filament\Teacher\Pages\ReferralsAndCommission;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentReferral;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\StudentReferralService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->freezeSecond();
    BillingSetting::create(['referral_enabled' => true, 'referral_lock_days' => 90, 'referral_change_days' => 1]);

    $this->referrals = app(ReferralService::class);
    $this->studentReferrals = app(StudentReferralService::class);
    $this->student = User::factory()->student()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();

    $this->current = fn (): ?StudentReferral => $this->studentReferrals->current($this->student);
    $this->examBy = fn (User $teacher): Exam => Exam::factory()->published()->create(['created_by' => $teacher->id]);
});

it('puts a logged-in student under the teacher whose exam link they open, fixed for 90 days', function () {
    $exam = ($this->examBy)($this->teacher);

    $this->actingAs($this->student)->get(route('guest-exam.join', $exam->share_token))->assertRedirect();

    $referral = ($this->current)();
    expect($referral->referrer_id)->toBe($this->teacher->id)
        ->and($referral->source)->toBe(ReferralSource::Exam)
        ->and($referral->exam_id)->toBe($exam->id)
        ->and($referral->isChangeable())->toBeFalse()
        ->and($referral->ends_at->equalTo(now()->addDays(90)))->toBeTrue()
        ->and($referral->daysLeft())->toBe(90);
});

it('keeps the first teacher for 90 days, whatever other exam links the student opens, then lets the next one in', function () {
    ExamAttempt::startFor(($this->examBy)($this->teacher), $this->student);

    $this->travel(89)->days();
    ExamAttempt::startFor(($this->examBy)($this->otherTeacher), $this->student);

    expect(($this->current)()->referrer_id)->toBe($this->teacher->id);
    expect($this->referrals->attach($this->student, $this->referrals->codeFor($this->otherTeacher)))->toBeFalse();

    $this->travel(2)->days();

    // The first referral has ended by itself; nothing was deleted.
    expect(($this->current)())->toBeNull();

    ExamAttempt::startFor(($this->examBy)($this->otherTeacher), $this->student);

    expect(($this->current)()->referrer_id)->toBe($this->otherTeacher->id)
        ->and(StudentReferral::count())->toBe(2);
});

it('lets a student change a code they typed for a day, counted from when they first typed one, then fixes it', function () {
    $friend = User::factory()->student()->create();

    expect($this->referrals->attach($this->student, $this->referrals->codeFor($friend)))->toBeTrue();
    $firstSet = ($this->current)();

    $this->travel(23)->hours();
    expect($this->referrals->attach($this->student, $this->referrals->codeFor($this->teacher)))->toBeTrue();

    $swapped = ($this->current)();
    expect($swapped->id)->toBe($firstSet->id)
        ->and($swapped->referrer_id)->toBe($this->teacher->id)
        // The 90 days still count from the day the first code was typed.
        ->and($swapped->ends_at->equalTo($firstSet->ends_at))->toBeTrue();

    $this->travel(2)->hours();
    expect($this->referrals->canEnterCode($this->student))->toBeFalse()
        ->and($this->referrals->attach($this->student, $this->referrals->codeFor($this->otherTeacher)))->toBeFalse()
        ->and(($this->current)()->referrer_id)->toBe($this->teacher->id);
});

it('lets a teacher\'s exam link replace a typed code still inside its change window, and fixes it from then', function () {
    $this->referrals->attach($this->student, $this->referrals->codeFor($this->otherTeacher));
    $this->travel(3)->hours();

    ExamAttempt::startFor(($this->examBy)($this->teacher), $this->student);

    $referral = ($this->current)();
    expect($referral->referrer_id)->toBe($this->teacher->id)
        ->and($referral->source)->toBe(ReferralSource::Exam)
        ->and($referral->isChangeable())->toBeFalse()
        ->and($referral->ends_at->equalTo(now()->addDays(90)))->toBeTrue()
        ->and(StudentReferral::count())->toBe(1);
});

it('does not let an exam link replace a typed code once it is fixed', function () {
    $this->referrals->attach($this->student, $this->referrals->codeFor($this->otherTeacher));
    $this->travel(2)->days();

    ExamAttempt::startFor(($this->examBy)($this->teacher), $this->student);

    expect(($this->current)()->referrer_id)->toBe($this->otherTeacher->id);
});

it('puts nobody under a referral from a self-practice exam, a guest attempt, or while the programme is off', function () {
    ExamAttempt::startFor(Exam::factory()->selfPractice()->published()->create(), $this->student);
    ExamAttempt::startForGuest(($this->examBy)($this->teacher), 'Guest', '01712345678');

    BillingSetting::current()->update(['referral_enabled' => false]);
    ExamAttempt::startFor(($this->examBy)($this->teacher), $this->student);

    expect(StudentReferral::count())->toBe(0);
});

it('lets the admin\'s numbers decide how long a referral lasts and can be changed', function () {
    BillingSetting::current()->update(['referral_lock_days' => 30, 'referral_change_days' => 0]);

    $this->referrals->attach($this->student, $this->referrals->codeFor($this->teacher));

    expect(($this->current)()->ends_at->equalTo(now()->addDays(30)))->toBeTrue()
        ->and($this->referrals->canEnterCode($this->student))->toBeFalse();
});

it('shows the teacher their students with the days left, never their full email', function () {
    ExamAttempt::startFor(($this->examBy)($this->teacher), $this->student);
    $this->travel(10)->days();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    livewire(ReferralsAndCommission::class)
        ->assertCanSeeTableRecords([($this->current)()])
        ->assertSee($this->student->name)
        ->assertSee($this->student->maskedEmail())
        ->assertDontSee($this->student->email)
        ->assertTableColumnStateSet('ends_at', 80, ($this->current)());
});
