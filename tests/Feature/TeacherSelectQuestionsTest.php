<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Topic;
use App\Models\User;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 1]);

    $this->filters = fn (array $overrides = []): array => [
        'exam_mode' => ExamDeliveryMode::Offline->value,
        'academic_class_id' => $this->classSubject->academic_class_id,
        'class_subject_id' => $this->classSubject->id,
        'chapter_id' => $this->chapter->id,
        'topic_id' => null,
        'question_type' => SelectQuestions::TYPE_BOTH,
        ...$overrides,
    ];

    $this->question = fn (array $attributes = [], ?Chapter $chapter = null) => Question::factory()
        ->approved()
        ->for($chapter ?? $this->chapter)
        ->create($attributes);

    $this->listedIds = fn ($page): array => $page->instance()->questions?->pluck('id')->all() ?? [];
});

it('lists nothing until a chapter is chosen', function () {
    ($this->question)();

    $page = livewire(SelectQuestions::class);

    expect($page->instance()->questions)->toBeNull();
});

it('lists only the approved, latest questions of the chosen chapter', function () {
    $approved = ($this->question)();
    $pending = Question::factory()->for($this->chapter)->create();
    $rejected = Question::factory()->rejected()->for($this->chapter)->create();
    $oldVersion = ($this->question)(['is_latest' => false]);
    $otherChapter = ($this->question)(chapter: Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]));

    $page = livewire(SelectQuestions::class)->set('data', ($this->filters)());

    expect(($this->listedIds)($page))->toBe([$approved->id]);
});

it('shows the correct mcq answer and the cq sub-questions', function () {
    ($this->question)([
        'question_text' => '<p>Capital of Bangladesh?</p>',
        'options' => [
            ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
            ['option' => 'Khulna', 'image' => null, 'is_correct' => false],
        ],
    ]);
    $cq = Question::factory()->cq()->approved()->for($this->chapter)->create(['question_text' => '<p>Read the stimulus.</p>']);
    $cq->cqParts()->create(['part_type' => 'knowledge', 'part_order' => 1, 'part_text' => '<p>What is a cell?</p>', 'marks' => 1]);

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)())
        ->assertSee('Capital of Bangladesh?')
        ->assertSeeHtml('qb-question-option--correct')
        ->assertSee('Read the stimulus.')
        ->assertSee('What is a cell?');
});

it('does not list questions of a chapter that belongs to another subject', function () {
    $foreignChapter = Chapter::factory()->create();
    ($this->question)(chapter: $foreignChapter);

    $page = livewire(SelectQuestions::class)->set('data', ($this->filters)(['chapter_id' => $foreignChapter->id]));

    expect($page->instance()->questions)->toBeNull();
});

it('filters by question type', function () {
    $mcq = ($this->question)();
    $cq = Question::factory()->cq()->approved()->for($this->chapter)->create();

    $mcqOnly = livewire(SelectQuestions::class)->set('data', ($this->filters)(['question_type' => SelectQuestions::TYPE_MCQ]));
    $cqOnly = livewire(SelectQuestions::class)->set('data', ($this->filters)(['question_type' => SelectQuestions::TYPE_CQ]));
    $both = livewire(SelectQuestions::class)->set('data', ($this->filters)());

    expect(($this->listedIds)($mcqOnly))->toBe([$mcq->id]);
    expect(($this->listedIds)($cqOnly))->toBe([$cq->id]);
    expect(($this->listedIds)($both))->toBe([$mcq->id, $cq->id]);
});

it('narrows mcq by topic but always keeps every cq of the chapter', function () {
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Cells', 'order_index' => 1]);
    $mcqInTopic = ($this->question)(['topic_id' => $topic->id]);
    $mcqOutsideTopic = ($this->question)();
    $cq = Question::factory()->cq()->approved()->for($this->chapter)->create();

    $both = livewire(SelectQuestions::class)->set('data', ($this->filters)(['topic_id' => $topic->id]));
    $mcqOnly = livewire(SelectQuestions::class)->set('data', ($this->filters)(['topic_id' => $topic->id, 'question_type' => SelectQuestions::TYPE_MCQ]));
    $cqOnly = livewire(SelectQuestions::class)->set('data', ($this->filters)(['topic_id' => $topic->id, 'question_type' => SelectQuestions::TYPE_CQ]));

    expect(($this->listedIds)($both))->toBe([$mcqInTopic->id, $cq->id]);
    expect(($this->listedIds)($mcqOnly))->toBe([$mcqInTopic->id]);
    expect(($this->listedIds)($cqOnly))->toBe([$cq->id]);
});

it('never lists cq for an online exam, whatever question type is asked for', function () {
    $mcq = ($this->question)();
    Question::factory()->cq()->approved()->for($this->chapter)->create();

    $page = livewire(SelectQuestions::class)->set('data', ($this->filters)(['exam_mode' => ExamDeliveryMode::Online->value]));

    expect(($this->listedIds)($page))->toBe([$mcq->id]);
});

it('paginates the question list instead of loading the whole chapter', function () {
    Question::factory()->approved()->for($this->chapter)->count(25)->create();

    $page = livewire(SelectQuestions::class)->set('data', ($this->filters)());

    expect($page->instance()->questions->count())->toBe(20);
    expect($page->instance()->questions->total())->toBe(25);
});

it('offers to select every question of the page at once, handing the browser that page only', function () {
    $questions = Question::factory()->approved()->for($this->chapter)->count(25)->create(['marks' => 2]);

    $page = livewire(SelectQuestions::class)->set('data', ($this->filters)());

    $page->assertSee('Select all on this page')
        ->assertSee('selectAll(page)', false);

    // What the button works on is written into the page as a JS string
    // holding JSON: unwrap the string, then read the JSON.
    preg_match('/x-data="\{ page: JSON\.parse\(\'(.*?)\'\) \}"/s', $page->html(), $matches);
    $handedOver = json_decode(json_decode('"'.$matches[1].'"'), true);

    // The first page's questions, and not the 21st.
    expect(array_column($handedOver, 'id'))->toBe($questions->take(20)->pluck('id')->all())
        ->and($handedOver[0])->toMatchArray(['chapter' => $this->chapter->id, 'type' => 'mcq', 'marks' => 2]);
});

it('reviews only the ids that are still selectable and tells the browser which survived', function () {
    $kept = ($this->question)();
    $pending = Question::factory()->for($this->chapter)->create();
    $otherSubject = ($this->question)(chapter: Chapter::factory()->create());

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)())
        ->call('review', [$kept->id, $pending->id, $otherSubject->id, 999999, 'not-an-id'])
        ->assertSet('step', SelectQuestions::STEP_REVIEW)
        ->assertSet('reviewIds', [$kept->id])
        ->assertDispatched('qb-selection-validated', ids: [$kept->id]);
});

it('merges the reviewed questions chapter by chapter in chapter order', function () {
    $secondChapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 2]);
    $fromSecond = ($this->question)(chapter: $secondChapter);
    $fromFirst = ($this->question)();

    $page = livewire(SelectQuestions::class)
        ->set('data', ($this->filters)())
        ->call('review', [$fromSecond->id, $fromFirst->id]);

    expect($page->instance()->reviewChapters->keys()->all())->toBe([$this->chapter->id, $secondChapter->id]);
});

it('saves the selection as a draft exam for the chosen class and subject', function () {
    $secondChapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 2]);
    $mcq = ($this->question)(['marks' => 1]);
    $cq = Question::factory()->cq()->approved()->for($secondChapter)->create();
    $cq->cqParts()->create(['part_type' => 'knowledge', 'part_order' => 1, 'part_text' => '<p>Part</p>', 'marks' => 4]);

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)())
        ->callAction('saveExam', data: ['title' => 'Half yearly', 'duration_minutes' => 90], arguments: ['ids' => [$cq->id, $mcq->id]])
        ->assertHasNoActionErrors()
        ->assertSet('step', SelectQuestions::STEP_SAVED)
        ->assertDispatched('qb-selection-saved');

    $exam = Exam::sole();

    expect($exam->title)->toBe('Half yearly');
    expect($exam->created_by)->toBe($this->teacher->id);
    expect($exam->exam_type)->toBe(ExamType::TeacherExam);
    expect($exam->status)->toBe(ExamStatus::Draft);
    expect($exam->delivery_mode)->toBe(ExamDeliveryMode::Offline);
    expect($exam->class_subject_id)->toBe($this->classSubject->id);
    expect($exam->subject_id)->toBe($this->classSubject->subject_id);
    expect($exam->questions->pluck('id')->all())->toBe([$mcq->id, $cq->id]);
    expect((float) $exam->total_marks)->toBe(5.0);
});

it('refuses to save a selection that contains a question outside the approved pool', function () {
    $approved = ($this->question)();
    $pending = Question::factory()->for($this->chapter)->create();

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)())
        ->callAction('saveExam', data: ['title' => 'Quiz', 'duration_minutes' => 30], arguments: ['ids' => [$approved->id, $pending->id]])
        ->assertNotified()
        ->assertNotSet('step', SelectQuestions::STEP_SAVED);

    expect(Exam::count())->toBe(0);
});

it('refuses to save cq questions into an online exam', function () {
    $cq = Question::factory()->cq()->approved()->for($this->chapter)->create();

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)(['exam_mode' => ExamDeliveryMode::Online->value]))
        ->callAction('saveExam', data: ['title' => 'Quiz', 'duration_minutes' => 30], arguments: ['ids' => [$cq->id]])
        ->assertNotified();

    expect(Exam::count())->toBe(0);
});

it('blocks saving once the monthly exam limit is reached', function () {
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 1]);
    Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);
    Exam::factory()->create(['created_by' => $this->teacher->id]);

    $question = ($this->question)();

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)())
        ->callAction('saveExam', data: ['title' => 'Quiz', 'duration_minutes' => 30], arguments: ['ids' => [$question->id]])
        ->assertNotified();

    expect(Exam::count())->toBe(1);
});

it('lets the exam that used the last free slot still be published, but not one beyond the limit', function () {
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 1]);
    Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);

    $question = ($this->question)(['question_type' => QuestionType::Mcq]);

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)(['exam_mode' => ExamDeliveryMode::Online->value]))
        ->callAction('saveExam', data: ['title' => 'Quiz', 'duration_minutes' => 30], arguments: ['ids' => [$question->id]])
        ->assertHasNoActionErrors();

    $withinLimit = Exam::sole();
    $beyondLimit = Exam::factory()->create(['created_by' => $this->teacher->id]);

    expect($this->teacher->can('publish', $withinLimit))->toBeTrue();
    expect($this->teacher->can('publish', $beyondLimit))->toBeFalse();
});

it('offers publish only for exams taken online and print only for exams taken offline', function () {
    $online = Exam::factory()->delivery(ExamDeliveryMode::Online)->create(['created_by' => $this->teacher->id]);
    $offline = Exam::factory()->delivery(ExamDeliveryMode::Offline)->create(['created_by' => $this->teacher->id]);
    $both = Exam::factory()->delivery(ExamDeliveryMode::Both)->create(['created_by' => $this->teacher->id]);

    livewire(ListExams::class)
        ->assertTableActionVisible('publish', $online)
        ->assertTableActionHidden('print', $online)
        ->assertTableActionHidden('publish', $offline)
        ->assertTableActionVisible('print', $offline)
        ->assertTableActionVisible('publish', $both)
        ->assertTableActionVisible('print', $both);
});

it('restores the filters for the subject a saved selection belongs to', function () {
    livewire(SelectQuestions::class)
        ->call('restoreFilters', ['class_subject_id' => $this->classSubject->id, 'exam_mode' => ExamDeliveryMode::Both->value])
        ->assertSet('data.class_subject_id', $this->classSubject->id)
        ->assertSet('data.academic_class_id', $this->classSubject->academic_class_id)
        ->assertSet('data.exam_mode', ExamDeliveryMode::Both->value)
        ->assertSet('data.chapter_id', null);
});

it('saves the end time chosen while saving an online exam, and ignores one for a print-only exam', function () {
    $this->freezeTime();
    $endsAt = now()->addDays(2)->startOfMinute();
    $question = ($this->question)();

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)(['exam_mode' => ExamDeliveryMode::Online->value]))
        ->callAction('saveExam', data: ['title' => 'Online', 'duration_minutes' => 30, 'link_expires_at' => $endsAt->toDateTimeString()], arguments: ['ids' => [$question->id]])
        ->assertHasNoActionErrors();

    livewire(SelectQuestions::class)
        ->set('data', ($this->filters)(['exam_mode' => ExamDeliveryMode::Offline->value]))
        ->callAction('saveExam', data: ['title' => 'Offline', 'duration_minutes' => 30, 'link_expires_at' => $endsAt->toDateTimeString()], arguments: ['ids' => [$question->id]])
        ->assertHasNoActionErrors();

    expect(Exam::where('title', 'Online')->sole()->link_expires_at->equalTo($endsAt))->toBeTrue();
    expect(Exam::where('title', 'Offline')->sole()->link_expires_at)->toBeNull();
});

describe('editing an exam in the picker', function () {
    beforeEach(function () {
        $this->kept = ($this->question)(['marks' => 1]);
        $this->removed = ($this->question)(['marks' => 2]);

        $this->exam = app(TeacherExamBuilder::class)->build(
            $this->teacher, $this->classSubject, ExamDeliveryMode::Online,
            [$this->kept->id, $this->removed->id], 'Original title', 30,
        );

        $this->editPage = function (?Exam $exam = null) {
            Livewire::withQueryParams(['exam' => ($exam ?? $this->exam)->id]);

            return livewire(SelectQuestions::class);
        };
    });

    it('opens the exam with its mode, class, subject and questions already in place', function () {
        $page = ($this->editPage)();

        $page->assertSet('editingExamId', $this->exam->id)
            ->assertSet('data.exam_mode', ExamDeliveryMode::Online->value)
            ->assertSet('data.class_subject_id', $this->classSubject->id)
            ->assertSet('data.academic_class_id', $this->classSubject->academic_class_id);

        $initial = $page->instance()->initialSelection();

        expect(array_keys($initial['items']))->toEqualCanonicalizing([$this->kept->id, $this->removed->id]);
        expect($initial['items'][$this->removed->id])->toBe(['c' => $this->chapter->id, 't' => 'mcq', 'm' => 2.0]);
        expect($initial['targets'])->toBe(['mcq' => 2, 'cq' => null]);
        expect($initial['chapters'])->toBe([$this->chapter->id => $this->chapter->name]);
    });

    it('opens straight on the final view, showing the questions the exam already has', function () {
        $this->kept->update(['question_text' => '<p>Kept question text</p>']);
        $this->removed->update(['question_text' => '<p>Other question text</p>']);

        ($this->editPage)()
            ->assertSet('step', SelectQuestions::STEP_REVIEW)
            ->assertSet('reviewIds', [$this->kept->id, $this->removed->id])
            ->assertSee('Kept question text')
            ->assertSee('Other question text')
            ->assertSee('Select more questions');
    });

    it('goes back to the chapters to add more, with the class and subject still set', function () {
        ($this->editPage)()
            ->call('backToSelection')
            ->assertSet('step', SelectQuestions::STEP_SELECT)
            ->assertSet('data.class_subject_id', $this->classSubject->id)
            ->assertSet('editingExamId', $this->exam->id);
    });

    it('keeps the edit selection apart from a new exam being built', function () {
        $editing = ($this->editPage)()->instance()->selectionStorageKey();

        Livewire::withQueryParams([]);
        $building = livewire(SelectQuestions::class)->instance()->selectionStorageKey();

        expect($editing)->not->toBe($building);
        expect($editing)->toContain((string) $this->exam->id);
    });

    it('adds and removes questions and renames the exam without creating a second one', function () {
        $added = ($this->question)(['marks' => 5]);

        ($this->editPage)()
            ->callAction('saveExam', data: ['title' => 'New title', 'duration_minutes' => 45], arguments: ['ids' => [$this->kept->id, $added->id]])
            ->assertHasNoActionErrors()
            ->assertSet('step', SelectQuestions::STEP_SAVED)
            ->assertDispatched('qb-selection-saved');

        $exam = Exam::sole();

        expect($exam->id)->toBe($this->exam->id);
        expect($exam->title)->toBe('New title');
        expect($exam->duration_minutes)->toBe(45);
        expect($exam->questions->pluck('id')->all())->toEqualCanonicalizing([$this->kept->id, $added->id]);
        expect((float) $exam->total_marks)->toBe(6.0);
    });

    it('does not count an edit against the monthly limit again', function () {
        $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 1]);
        Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);

        ($this->editPage)()
            ->callAction('saveExam', data: ['title' => 'Still allowed', 'duration_minutes' => 30], arguments: ['ids' => [$this->kept->id]])
            ->assertSet('step', SelectQuestions::STEP_SAVED);

        expect(Exam::sole()->title)->toBe('Still allowed');
    });

    it('keeps the share link, status and end time of the exam being edited', function () {
        $this->exam->publish();
        $this->exam->closeLinkAt(now()->addDay()->startOfMinute());
        $before = $this->exam->fresh();

        ($this->editPage)()
            ->callAction('saveExam', data: ['title' => 'Edited', 'duration_minutes' => 30], arguments: ['ids' => [$this->kept->id]]);

        $after = Exam::sole();

        expect($after->share_token)->toBe($before->share_token);
        expect($after->status)->toBe($before->status);
        expect($after->link_expires_at->equalTo($before->link_expires_at))->toBeTrue();
    });

    it('refuses to change the questions once a student has started the exam', function () {
        ExamAttempt::create(['exam_id' => $this->exam->id, 'is_guest' => true, 'guest_name' => 'Rahim', 'guest_contact' => '01700000001', 'started_at' => now()]);

        ($this->editPage)()
            ->callAction('saveExam', data: ['title' => 'Changed', 'duration_minutes' => 30], arguments: ['ids' => [$this->kept->id]])
            ->assertNotified()
            ->assertNotSet('step', SelectQuestions::STEP_SAVED);

        expect(Exam::sole()->questions)->toHaveCount(2);
        expect(Exam::sole()->title)->toBe('Original title');
    });

    it('still applies every selection check when editing', function () {
        $pending = Question::factory()->for($this->chapter)->create();

        ($this->editPage)()
            ->callAction('saveExam', data: ['title' => 'Changed', 'duration_minutes' => 30], arguments: ['ids' => [$this->kept->id, $pending->id]])
            ->assertNotified();

        expect(Exam::sole()->questions->pluck('id')->all())->toEqualCanonicalizing([$this->kept->id, $this->removed->id]);
    });

    it('does not open another teacher\'s exam for editing', function () {
        ($this->editPage)(Exam::factory()->create());
    })->throws(ModelNotFoundException::class);

    it('offers Edit in the exams list only while nobody has started the exam', function () {
        $started = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
        ExamAttempt::create(['exam_id' => $started->id, 'is_guest' => true, 'guest_name' => 'Rahim', 'guest_contact' => '01700000001', 'started_at' => now()]);

        Livewire::withQueryParams([]);

        livewire(ListExams::class)
            ->assertTableActionVisible('editQuestions', $this->exam)
            ->assertTableActionHasUrl('editQuestions', SelectQuestions::getUrl(['exam' => $this->exam->id]), $this->exam)
            ->assertTableActionHidden('editQuestions', $started);
    });
});
