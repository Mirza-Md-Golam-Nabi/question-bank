<?php

use App\Enums\ExamStatus;
use App\Filament\Teacher\Resources\Exams\Pages\CreateExam;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));
});

it('creates a draft exam from the approved pool of a chosen subject', function () {
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create();
    $question = Question::factory()->approved()->for($chapter)->create();

    // Make the question resolvable under the chosen subject by aligning the
    // chapter's class_subject to it.
    $chapter->classSubject()->update(['subject_id' => $subject->id]);

    livewire(CreateExam::class)
        ->fillForm([
            'title' => 'Midterm',
            'subject_id' => $subject->id,
            'duration_minutes' => 60,
            'questions' => [$question->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $exam = Exam::first();
    expect($exam->title)->toBe('Midterm');
    expect($exam->status)->toBe(ExamStatus::Draft);
    expect($exam->created_by)->toBe($this->teacher->id);
    expect($exam->questions)->toHaveCount(1);
});

it('publishes an exam, generating a share token and recalculating total marks', function () {
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 5]);
    Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);

    $exam = Exam::factory()->create(['created_by' => $this->teacher->id]);
    $question = Question::factory()->approved()->for(Chapter::factory())->create(['marks' => 4]);
    $exam->questions()->attach([$question->id => ['order_index' => 1, 'marks_override' => null]]);

    livewire(ListExams::class)
        ->callTableAction('publish', $exam)
        ->assertHasNoTableActionErrors();

    $exam->refresh();
    expect($exam->status)->toBe(ExamStatus::Published);
    expect($exam->share_token)->not->toBeNull();
    expect((float) $exam->total_marks)->toBe(4.0);
});

it('does not offer the publish action once the monthly limit is reached', function () {
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 1]);
    Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);

    Exam::factory()->create(['created_by' => $this->teacher->id]);
    $secondExam = Exam::factory()->create(['created_by' => $this->teacher->id]);

    livewire(ListExams::class)
        ->assertTableActionHidden('publish', $secondExam);
});
