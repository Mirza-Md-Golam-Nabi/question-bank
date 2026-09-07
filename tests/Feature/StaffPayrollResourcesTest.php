<?php

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentMethod;
use App\Enums\UserStatus;
use App\Filament\Resources\StaffPayouts\Pages\ManageStaffPayouts;
use App\Filament\Resources\Staffs\Pages\ManageStaffs;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Filament\Staff\Pages\ApprovedQuestionsBreakdown;
use App\Filament\Staff\Pages\MonthlyEarnings;
use App\Filament\Staff\Pages\MyEarnings;
use App\Filament\Staff\Pages\PayoutHistory;
use App\Filament\Staff\Pages\PendingQuestionsBreakdown;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\QuestionRate;
use App\Models\StaffEarning;
use App\Models\StaffPayout;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('lists only teacher-role users in the teacher resource', function () {
    $teacher = User::factory()->teacher()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($this->admin);

    livewire(ManageTeachers::class)
        ->assertCanSeeTableRecords([$teacher])
        ->assertCanNotSeeTableRecords([$staff]);
});

it('lets admin approve a pending staff member from the staff resource', function () {
    $staff = User::factory()->staff()->pending()->googleAuthenticated()->create();

    $this->actingAs($this->admin);

    livewire(ManageStaffs::class)
        ->callTableAction('approve', $staff)
        ->assertHasNoTableActionErrors();

    expect($staff->refresh()->status)->toBe(UserStatus::Active);
});

it('lets admin suspend an active staff member from the staff resource', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($this->admin);

    livewire(ManageStaffs::class)
        ->callTableAction('suspend', $staff)
        ->assertHasNoTableActionErrors();

    expect($staff->refresh()->status)->toBe(UserStatus::Suspended);
});

it('lets admin reactivate a suspended staff member from the staff resource', function () {
    $staff = User::factory()->staff()->suspended()->create();

    $this->actingAs($this->admin);

    livewire(ManageStaffs::class)
        ->callTableAction('reactivate', $staff)
        ->assertHasNoTableActionErrors();

    expect($staff->refresh()->status)->toBe(UserStatus::Active);
});

it('lets admin permanently suspend a staff member from the staff resource', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($this->admin);

    livewire(ManageStaffs::class)
        ->callTableAction('permanentSuspend', $staff)
        ->assertHasNoTableActionErrors();

    expect($staff->refresh()->status)->toBe(UserStatus::PermanentSuspend);
});

it('lets admin reactivate a permanently suspended staff member from the staff resource', function () {
    $staff = User::factory()->staff()->permanentlySuspended()->create();

    $this->actingAs($this->admin);

    livewire(ManageStaffs::class)
        ->callTableAction('reactivate', $staff)
        ->assertHasNoTableActionErrors();

    expect($staff->refresh()->status)->toBe(UserStatus::Active);
});

it('batch-pays a staff member from the payout resource header action', function () {
    $staff = User::factory()->staff()->create();
    StaffProfile::factory()->for($staff, 'user')->create(['total_paid' => 0]);
    StaffEarning::factory()->count(2)->create(['staff_id' => $staff->id, 'amount' => 10]);

    $this->actingAs($this->admin);

    livewire(ManageStaffPayouts::class)
        ->callAction('createPayout', data: [
            'staff_id' => $staff->id,
            'reference_note' => 'Test payout',
        ])
        ->assertHasNoActionErrors();

    expect((float) $staff->staffProfile->refresh()->total_paid)->toBe(20.0);
});

it('lets a staff member save their bank info and see their earnings summary', function () {
    $staff = User::factory()->staff()->create();
    StaffEarning::factory()->create(['staff_id' => $staff->id, 'amount' => 12]);

    $this->actingAs($staff);

    livewire(MyEarnings::class)
        ->fillForm([
            'payment_method' => PaymentMethod::Bank->value,
            'account_holder_name' => 'John Doe',
            'bank_name' => 'ABC Bank',
            'bank_account_number' => '1234567890',
        ])
        ->call('saveBankInfo')
        ->assertHasNoFormErrors();

    $profile = StaffProfile::where('user_id', $staff->id)->first();
    expect($profile->payment_method)->toBe(PaymentMethod::Bank);
    expect($profile->account_holder_name)->toBe('John Doe');
    expect($profile->mobile_banking_provider)->toBeNull();
    expect($profile->mobile_banking_number)->toBeNull();
});

it('lets a staff member save mobile banking info instead of bank info, clearing any old bank details', function () {
    $staff = User::factory()->staff()->create();
    StaffProfile::factory()->for($staff, 'user')->create([
        'payment_method' => PaymentMethod::Bank,
        'account_holder_name' => 'Old Name',
        'bank_name' => 'Old Bank',
    ]);

    $this->actingAs($staff);

    livewire(MyEarnings::class)
        ->fillForm([
            'payment_method' => PaymentMethod::MobileBanking->value,
            'mobile_banking_provider' => MobileBankingProvider::Bkash->value,
            'mobile_banking_number' => '01711223344',
        ])
        ->call('saveBankInfo')
        ->assertHasNoFormErrors();

    $profile = StaffProfile::where('user_id', $staff->id)->first();
    expect($profile->payment_method)->toBe(PaymentMethod::MobileBanking);
    expect($profile->mobile_banking_provider)->toBe(MobileBankingProvider::Bkash);
    expect($profile->mobile_banking_number)->toBe('01711223344');
    expect($profile->account_holder_name)->toBeNull();
    expect($profile->bank_name)->toBeNull();
});

it('defaults the payment method to Mobile Banking for a staff member with no saved preference yet', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    livewire(MyEarnings::class)
        ->assertFormSet(['payment_method' => PaymentMethod::MobileBanking]);
});

it('keeps showing a staff member\'s previously saved Bank preference instead of defaulting back to Mobile Banking', function () {
    $staff = User::factory()->staff()->create();
    StaffProfile::factory()->for($staff, 'user')->create(['payment_method' => PaymentMethod::Bank]);

    $this->actingAs($staff);

    livewire(MyEarnings::class)
        ->assertFormSet(['payment_method' => PaymentMethod::Bank]);
});

it('records earnings and shows the correct totals even when the staff member never saved bank info first', function () {
    // No StaffProfile row exists yet for this staff — they've never visited
    // "save bank info". Approving their question must not silently drop the
    // earnings summary because of that missing row.
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 5, 'effective_from' => now()->subDay()]);

    $staff = User::factory()->staff()->create();
    $question = Question::factory()->create(['created_by' => $staff->id]);

    $this->actingAs($this->admin);
    $question->approve($this->admin);

    expect(StaffProfile::where('user_id', $staff->id)->first())
        ->not->toBeNull()
        ->total_questions_approved->toBe(1)
        ->and((float) StaffProfile::where('user_id', $staff->id)->first()->total_earned)->toBeGreaterThan(0);

    $this->actingAs($staff);
    $earningsPage = new MyEarnings;

    expect($earningsPage->totalQuestionsApproved())->toBe(1);
    expect($earningsPage->totalEarned())->toBeGreaterThan(0.0);
});

it('breaks earnings down by class + subject, keeping the same subject in two classes separate', function () {
    // Physics exists in both Class 9 and Class 10 as two distinct
    // class_subjects rows (CLAUDE.md: subjects are class-independent, each
    // class keeps its own chapter set) — grouping by subject name alone
    // would wrongly merge these into a single row and hide which class the
    // earning came from.
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 5, 'effective_from' => now()->subDay()]);

    $staff = User::factory()->staff()->create();
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $classNine = AcademicClass::factory()->create(['name' => 'Class 9']);
    $classTen = AcademicClass::factory()->create(['name' => 'Class 10']);

    $chapterNine = Chapter::factory()->create([
        'class_subject_id' => ClassSubject::factory()->create(['academic_class_id' => $classNine->id, 'subject_id' => $subject->id]),
    ]);
    $chapterTen = Chapter::factory()->create([
        'class_subject_id' => ClassSubject::factory()->create(['academic_class_id' => $classTen->id, 'subject_id' => $subject->id]),
    ]);

    $this->actingAs($this->admin);
    Question::factory()->create(['created_by' => $staff->id, 'chapter_id' => $chapterNine->id])->approve($this->admin);
    Question::factory()->create(['created_by' => $staff->id, 'chapter_id' => $chapterTen->id])->approve($this->admin);

    $this->actingAs($staff);
    $breakdown = (new MyEarnings)->subjectBreakdown();

    expect($breakdown)->toHaveCount(2);
    expect($breakdown->pluck('class')->sort()->values()->all())->toBe(['Class 10', 'Class 9']);
    expect($breakdown->every(fn (array $row) => $row['subject'] === 'Physics'))->toBeTrue();
});

it('groups earnings by month across the last 24 months, ignoring anything older', function () {
    $staff = User::factory()->staff()->create();
    $question = Question::factory()->create(['created_by' => $staff->id]);

    StaffEarning::factory()->create(['staff_id' => $staff->id, 'question_id' => $question->id, 'amount' => 10]);

    $lastMonthEarning = StaffEarning::factory()->create(['staff_id' => $staff->id, 'question_id' => $question->id, 'amount' => 25]);
    $lastMonthEarning->created_at = now()->subMonthsNoOverflow();
    $lastMonthEarning->save();

    // Outside the 24-month window — must not be counted anywhere.
    $tooOldEarning = StaffEarning::factory()->create(['staff_id' => $staff->id, 'question_id' => $question->id, 'amount' => 999]);
    $tooOldEarning->created_at = now()->subMonthsNoOverflow(30);
    $tooOldEarning->save();

    $this->actingAs($staff);
    $months = (new MonthlyEarnings)->monthlyEarnings();

    expect($months)->toHaveCount(24);
    expect($months[0]['label'])->toBe(now()->format('M \'y'));
    expect($months[0]['total'])->toBe(10.0);
    expect($months[1]['total'])->toBe(25.0);
    expect($months->sum('total'))->toBe(35.0);
});

it('opens the monthly earnings drill-down without error', function () {
    $staff = User::factory()->staff()->create();
    StaffEarning::factory()->create(['staff_id' => $staff->id, 'amount' => 10]);

    $this->actingAs($staff);

    livewire(MonthlyEarnings::class)->assertOk();
});

it('lists a staff member\'s own payouts on the payout history drill-down, and not another staff member\'s', function () {
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();

    $this->actingAs($this->admin);
    StaffEarning::factory()->create(['staff_id' => $staff->id, 'amount' => 10]);
    StaffPayout::createFor($staff, $this->admin, 'August batch');

    StaffEarning::factory()->create(['staff_id' => $otherStaff->id, 'amount' => 15]);
    StaffPayout::createFor($otherStaff, $this->admin, 'Someone else\'s payout');

    $this->actingAs($staff);
    $payouts = (new PayoutHistory)->payouts();

    expect($payouts)->toHaveCount(1);
    expect((float) $payouts->first()->total_amount)->toBe(10.0);
    expect($payouts->first()->reference_note)->toBe('August batch');
});

it('links all four summary cards to their drill-down pages', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff);

    $html = livewire(MyEarnings::class)->html();

    expect($html)->toContain(ApprovedQuestionsBreakdown::getUrl(panel: 'staff'));
    expect($html)->toContain(PendingQuestionsBreakdown::getUrl(panel: 'staff'));
    expect($html)->toContain(MonthlyEarnings::getUrl(panel: 'staff'));
    expect($html)->toContain(PayoutHistory::getUrl(panel: 'staff'));
});

it('opens the pending and approved questions drill-downs without error', function () {
    $staff = User::factory()->staff()->create();
    Question::factory()->create(['created_by' => $staff->id]);
    Question::factory()->approved()->create(['created_by' => $staff->id]);

    $this->actingAs($staff);

    livewire(PendingQuestionsBreakdown::class)->assertOk();
    livewire(ApprovedQuestionsBreakdown::class)->assertOk();
});

it('breaks pending and approved question counts down by class + subject, keeping the same subject in two classes separate', function () {
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $classNine = AcademicClass::factory()->create(['name' => 'Class 9']);
    $classTen = AcademicClass::factory()->create(['name' => 'Class 10']);

    $chapterNine = Chapter::factory()->create([
        'class_subject_id' => ClassSubject::factory()->create(['academic_class_id' => $classNine->id, 'subject_id' => $subject->id]),
    ]);
    $chapterTen = Chapter::factory()->create([
        'class_subject_id' => ClassSubject::factory()->create(['academic_class_id' => $classTen->id, 'subject_id' => $subject->id]),
    ]);

    $this->actingAs($staff);
    Question::factory()->count(2)->create(['created_by' => $staff->id, 'chapter_id' => $chapterNine->id]);
    Question::factory()->create(['created_by' => $staff->id, 'chapter_id' => $chapterTen->id]);
    // Another staff's pending question must never show up in this staff's breakdown.
    Question::factory()->create(['created_by' => $otherStaff->id, 'chapter_id' => $chapterNine->id]);

    $pendingBreakdown = (new PendingQuestionsBreakdown)->breakdown();

    expect($pendingBreakdown)->toHaveCount(2);
    expect($pendingBreakdown->firstWhere('class', 'Class 9')['count'])->toBe(2);
    expect($pendingBreakdown->firstWhere('class', 'Class 10')['count'])->toBe(1);
    expect($pendingBreakdown->every(fn (array $row) => $row['subject'] === 'Physics'))->toBeTrue();

    $this->actingAs($this->admin);
    Question::factory()->approved()->create(['created_by' => $staff->id, 'chapter_id' => $chapterNine->id]);

    $this->actingAs($staff);
    $approvedBreakdown = (new ApprovedQuestionsBreakdown)->breakdown();

    expect($approvedBreakdown)->toHaveCount(1);
    expect($approvedBreakdown->first()['class'])->toBe('Class 9');
    expect($approvedBreakdown->first()['count'])->toBe(1);
});

it('counts a staff member\'s own pending questions on the earnings page', function () {
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();

    $this->actingAs($staff);
    Question::factory()->count(2)->create(['created_by' => $staff->id]);
    Question::factory()->approved()->create(['created_by' => $staff->id]);
    Question::factory()->create(['created_by' => $otherStaff->id]);

    expect((new MyEarnings)->pendingQuestionsCount())->toBe(2);
});
