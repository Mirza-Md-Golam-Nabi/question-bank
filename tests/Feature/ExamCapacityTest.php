<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\PlanConditionType;
use App\Filament\Student\Pages\JoinExam;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TeacherExamBuilder;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->published()->create(['max_participants' => 2]);
});

it('copies the plan\'s participant limit onto an exam the teacher saves', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->withConditions([
        SubscriptionPlanFactory::condition(PlanConditionType::MaxParticipants, ['limit' => 50]),
    ])->create();
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);
    $classSubject = ClassSubject::factory()->create();
    $question = Question::factory()->approved()->for(Chapter::factory()->create(['class_subject_id' => $classSubject->id]))->create();

    $exam = app(TeacherExamBuilder::class)->build($teacher, $classSubject, ExamDeliveryMode::Online, [$question->id], 'Quiz', 20);

    expect($exam->max_participants)->toBe(50)
        ->and($plan->planConditions()->descriptions())->toContain('Up to 50 participants per online exam');
});

it('takes no one new once the exam is full, but lets those already in come back', function () {
    $first = ExamAttempt::startForGuest($this->exam, 'Rahim', '01711111111');
    ExamAttempt::startForGuest($this->exam, 'Karim', '01722222222');

    expect(fn () => ExamAttempt::startForGuest($this->exam, 'Third', '01733333333'))
        ->toThrow(ValidationException::class, 'This exam is full');

    expect(ExamAttempt::startForGuest($this->exam, 'Rahim', '01711111111')->id)->toBe($first->id)
        ->and(ExamAttempt::count())->toBe(2);
});

it('tells a guest and a logged-in student that the exam is full', function () {
    ExamAttempt::startForGuest($this->exam, 'Rahim', '01711111111');
    ExamAttempt::startForGuest($this->exam, 'Karim', '01722222222');

    $this->post(route('guest-exam.start', $this->exam->share_token), ['guest_name' => 'Third', 'guest_contact' => '01733333333'])
        ->assertSessionHasErrors('exam');

    $student = User::factory()->student()->create();
    $this->actingAs($student)
        ->get(route('guest-exam.join', $this->exam->share_token))
        ->assertRedirect(route('guest-exam.show', $this->exam->share_token))
        ->assertSessionHasErrors('exam');

    Filament::setCurrentPanel(Filament::getPanel('student'));
    livewire(JoinExam::class)
        ->fillForm(['share_token' => $this->exam->share_token])
        ->call('join')
        ->assertNotified();

    expect(ExamAttempt::count())->toBe(2);
});

it('has no limit on an exam saved without one', function () {
    $exam = Exam::factory()->published()->create();

    foreach (range(1, 3) as $i) {
        ExamAttempt::startForGuest($exam, "Guest {$i}", "0171111111{$i}");
    }

    expect(ExamAttempt::count())->toBe(3);
});
