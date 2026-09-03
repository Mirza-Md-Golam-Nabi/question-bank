<?php

use App\Enums\ExamType;
use App\Filament\Student\Pages\BuildPracticeExam;
use App\Filament\Student\Pages\GeneratePracticeExam;
use App\Filament\Student\Pages\JoinExam;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->student = User::factory()->student()->create();
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    $plan = SubscriptionPlan::factory()->forStudents()->create(['monthly_exam_limit' => 3]);
    Subscription::factory()->create(['user_id' => $this->student->id, 'plan_id' => $plan->id]);
});

it('auto-generates a practice exam from the approved pool and starts an attempt', function () {
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    Question::factory()->approved()->for($chapter)->count(5)->create();

    livewire(GeneratePracticeExam::class)
        ->fillForm([
            'subject_id' => $classSubject->subject_id,
            'question_count' => 3,
        ])
        ->call('generate');

    $exam = Exam::where('created_by', $this->student->id)->first();
    expect($exam)->not->toBeNull();
    expect($exam->exam_type)->toBe(ExamType::SelfPractice);
    expect($exam->generation_mode->value)->toBe('auto');
    expect($exam->questions)->toHaveCount(3);
    expect($exam->share_token)->toBeNull();

    expect(ExamAttempt::where('exam_id', $exam->id)->where('student_id', $this->student->id)->exists())->toBeTrue();
});

it('builds a manual practice exam from student-picked questions', function () {
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $questions = Question::factory()->approved()->for($chapter)->count(2)->create();

    livewire(BuildPracticeExam::class)
        ->fillForm([
            'subject_id' => $classSubject->subject_id,
            'question_ids' => $questions->pluck('id')->all(),
        ])
        ->call('build');

    $exam = Exam::where('created_by', $this->student->id)->first();
    expect($exam->generation_mode->value)->toBe('manual');
    expect($exam->questions)->toHaveCount(2);
});

it('counts auto and manual self-practice exams together against the same limit', function () {
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $questions = Question::factory()->approved()->for($chapter)->count(5)->create();

    // limit is 3 — generate 3 exams across both modes, the 4th must be blocked
    foreach (range(1, 3) as $i) {
        livewire(GeneratePracticeExam::class)
            ->fillForm(['subject_id' => $classSubject->subject_id, 'question_count' => 1])
            ->call('generate');
    }

    expect(Exam::where('created_by', $this->student->id)->count())->toBe(3);

    livewire(BuildPracticeExam::class)
        ->fillForm(['subject_id' => $classSubject->subject_id, 'question_ids' => [$questions->first()->id]])
        ->call('build');

    // still 3 — the 4th attempt was blocked by the monthly limit
    expect(Exam::where('created_by', $this->student->id)->count())->toBe(3);
});

it('lets a logged-in student join a shared exam by its token', function () {
    $exam = Exam::factory()->published()->create();

    livewire(JoinExam::class)
        ->fillForm(['share_token' => $exam->share_token])
        ->call('join');

    expect(ExamAttempt::where('exam_id', $exam->id)->where('student_id', $this->student->id)->exists())->toBeTrue();
});
