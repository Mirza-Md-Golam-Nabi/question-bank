<?php

use App\Enums\Difficulty;
use App\Enums\GenerationMode;
use App\Enums\PlanConditionType;
use App\Enums\QuestionType;
use App\Filament\Student\Pages\BuildPracticeExam;
use App\Filament\Student\Pages\TakeExamPage;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Topic;
use App\Models\User;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->classSubject = ClassSubject::factory()->create();
    $this->student = User::factory()->student()->create(['academic_class_id' => $this->classSubject->academic_class_id]);
    subscribeToEverything($this->student);
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 1]);

    $this->mcq = fn (array $attributes = [], ?Chapter $chapter = null): Question => Question::factory()->approved()->for($chapter ?? $this->chapter)->create([
        'question_type' => QuestionType::Mcq,
        'marks' => 1,
        ...$attributes,
    ]);

    $this->filters = fn (array $overrides = []): array => [
        'academic_class_id' => $this->classSubject->academic_class_id,
        'class_subject_id' => $this->classSubject->id,
        'chapter_id' => $this->chapter->id,
        'topic_id' => null,
        ...$overrides,
    ];

    $this->listedIds = fn ($page): array => $page->instance()->questions?->pluck('id')->all() ?? [];
});

it('lists only the approved, latest MCQs of the chosen chapter', function () {
    $approved = ($this->mcq)();
    Question::factory()->for($this->chapter)->create();
    Question::factory()->rejected()->for($this->chapter)->create();
    ($this->mcq)(['is_latest' => false]);
    ($this->mcq)(['question_type' => QuestionType::Cq]);

    $page = livewire(BuildPracticeExam::class)->set('data', ($this->filters)());

    expect(($this->listedIds)($page))->toBe([$approved->id]);
});

it('lists the whole chapter without a topic, and narrows it with one', function () {
    $topic = Topic::factory()->create(['chapter_id' => $this->chapter->id]);
    $inTopic = ($this->mcq)(['topic_id' => $topic->id]);
    $other = ($this->mcq)();

    $page = livewire(BuildPracticeExam::class)->set('data', ($this->filters)());
    expect(($this->listedIds)($page))->toBe([$inTopic->id, $other->id]);

    $page->set('data', ($this->filters)(['topic_id' => $topic->id]));
    expect(($this->listedIds)($page))->toBe([$inTopic->id]);
});

it('never shows the answers of the questions the student is about to sit', function () {
    ($this->mcq)([
        'options' => [
            ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
            ['option' => 'Khulna', 'image' => null, 'is_correct' => false],
        ],
    ]);

    livewire(BuildPracticeExam::class)
        ->set('data', ($this->filters)())
        ->assertSee('Dhaka')
        ->assertDontSeeHtml('qb-question-option--correct');
});

it('only browses the student\'s own class', function () {
    $otherClass = ClassSubject::factory()->create();
    ($this->mcq)(chapter: $otherChapter = Chapter::factory()->create(['class_subject_id' => $otherClass->id]));

    $page = livewire(BuildPracticeExam::class)->set('data', ($this->filters)([
        'academic_class_id' => $otherClass->academic_class_id,
        'class_subject_id' => $otherClass->id,
        'chapter_id' => $otherChapter->id,
    ]));

    expect($page->instance()->questions)->toBeNull()
        ->and($page->instance()->accessibleClassSubjectIds)->toBe([$this->classSubject->id]);
});

it('lists only the difficulties the student\'s plan allows', function () {
    Subscription::query()->where('user_id', $this->student->id)->delete();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->withConditions([
        SubscriptionPlanFactory::condition(PlanConditionType::Difficulties, ['difficulties' => [Difficulty::Easy->value]]),
    ])->create();
    Subscription::factory()->create(['user_id' => $this->student->id, 'plan_id' => $plan->id]);

    $easy = ($this->mcq)(['difficulty' => Difficulty::Easy]);
    ($this->mcq)(['difficulty' => Difficulty::Hard]);

    $page = livewire(BuildPracticeExam::class)->set('data', ($this->filters)());

    expect(($this->listedIds)($page))->toBe([$easy->id]);
});

it('reviews only the questions the student may really take', function () {
    $chosen = ($this->mcq)();
    $secondChapter = ($this->mcq)(chapter: Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 2]));
    $pending = Question::factory()->for($this->chapter)->create();
    $cq = ($this->mcq)(['question_type' => QuestionType::Cq]);

    $page = livewire(BuildPracticeExam::class)
        ->set('data', ($this->filters)())
        ->call('review', [$chosen->id, $secondChapter->id, $pending->id, $cq->id, 999999])
        ->assertDispatched('qb-selection-validated');

    expect($page->get('step'))->toBe(BuildPracticeExam::STEP_REVIEW)
        ->and(collect($page->get('reviewIds'))->sort()->values()->all())->toBe([$chosen->id, $secondChapter->id]);
});

it('starts a practice exam on the picked questions, from several chapters, timed by their number', function () {
    $questions = collect(range(1, 6))->map(fn (int $i) => ($this->mcq)(chapter: $i <= 3 ? $this->chapter : null));
    $otherChapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 2]);
    $questions->push(($this->mcq)(chapter: $otherChapter));

    $page = livewire(BuildPracticeExam::class)
        ->set('data', ($this->filters)())
        ->call('finish', $questions->pluck('id')->all())
        ->assertDispatched('qb-selection-saved');

    $exam = Exam::query()->where('created_by', $this->student->id)->sole();
    $page->assertRedirect(TakeExamPage::getUrl(['attempt' => $exam->attempts()->sole()->id]));

    expect($exam->generation_mode)->toBe(GenerationMode::Manual)
        ->and($exam->questions)->toHaveCount(7)
        ->and($exam->duration_minutes)->toBe(10);
});

it('starts nothing when none of the picked questions can be taken', function () {
    $pending = Question::factory()->for($this->chapter)->create();

    livewire(BuildPracticeExam::class)
        ->set('data', ($this->filters)())
        ->call('finish', [$pending->id])
        ->assertNotified(__('The exam could not be started'))
        ->assertNoRedirect();

    expect(Exam::query()->where('created_by', $this->student->id)->exists())->toBeFalse();
});

it('picks at random only from what the student may pick', function () {
    $approved = ($this->mcq)();
    Question::factory()->for($this->chapter)->create();

    $picked = livewire(BuildPracticeExam::class)
        ->set('data', ($this->filters)())
        ->instance()
        ->randomQuestions([], ['mcq' => 5, 'cq' => 5]);

    expect(collect($picked)->pluck('id')->all())->toBe([$approved->id]);
});

it('asks for the class first, and for a plan', function () {
    $this->student->update(['academic_class_id' => null]);

    livewire(BuildPracticeExam::class)->assertSee(__('Set your class first'));

    $this->student->update(['academic_class_id' => $this->classSubject->academic_class_id]);
    Subscription::query()->where('user_id', $this->student->id)->delete();

    livewire(BuildPracticeExam::class)->assertSee(__('You need a plan to practise'));
});

it('lists the questions oldest first, and latest first on "Latest"', function () {
    $questions = collect(range(1, 3))->map(fn () => ($this->mcq)());

    $page = livewire(BuildPracticeExam::class)->set('data', ($this->filters)());

    expect(($this->listedIds)($page))->toBe($questions->pluck('id')->all());

    $page->callAction('latest');

    expect(($this->listedIds)($page))->toBe($questions->pluck('id')->reverse()->values()->all());
});
