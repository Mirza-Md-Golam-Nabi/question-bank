<?php

use App\Enums\ExamStatus;
use App\Filament\Student\Pages\ExamResultPage;
use App\Filament\Student\Pages\MyResults;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->teacher = User::factory()->teacher()->create();
    $this->exam = Exam::factory()->published()->create(['created_by' => $this->teacher->id, 'title' => 'Half yearly']);
    $this->question = Question::factory()->approved()->for(Chapter::factory())->create([
        'question_text' => '<p>Capital of Bangladesh?</p>',
        'options' => [
            ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
            ['option' => 'Khulna', 'image' => null, 'is_correct' => false],
        ],
    ]);
    $this->exam->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);

    $this->startAsGuest = fn (string $name = 'Rahim Uddin', string $contact = '01712345678') => $this->post(
        route('guest-exam.start', $this->exam->share_token),
        ['guest_name' => $name, 'guest_contact' => $contact],
    );

    $this->findResult = fn (string $name, string $contact) => $this->post(
        route('guest-exam.result', $this->exam->share_token),
        ['guest_name' => $name, 'guest_contact' => $contact],
    );
});

describe('guest', function () {
    it('needs a phone or email to start, since that is the way back to the result', function () {
        $this->post(route('guest-exam.start', $this->exam->share_token), ['guest_name' => 'Rahim'])
            ->assertSessionHasErrors('guest_contact');

        expect(ExamAttempt::count())->toBe(0);
    });

    it('shows only the score on submit — not the questions, the options or the answers', function () {
        ($this->startAsGuest)();

        $this->post(route('guest-exam.submit', ExamAttempt::sole()), ['answers' => [$this->question->id => 'Khulna']])
            ->assertOk()
            ->assertSee('Score')
            ->assertSee('The answers are not available yet.')
            ->assertDontSee('Capital of Bangladesh?')
            ->assertDontSee('Dhaka')
            ->assertDontSee('Khulna')
            ->assertDontSee('qb-result-option', escape: false);
    });

    it('reveals nothing to someone who hands in a blank paper', function () {
        ($this->startAsGuest)();

        $this->post(route('guest-exam.submit', ExamAttempt::sole()))
            ->assertOk()
            ->assertDontSee('Dhaka');
    });

    it('finds the result again by name and contact, still hiding the answers until they are released', function () {
        ($this->startAsGuest)();
        $this->post(route('guest-exam.submit', ExamAttempt::sole()), ['answers' => [$this->question->id => 'Khulna']]);
        $this->flushSession();

        ($this->findResult)('Rahim Uddin', '01712345678')
            ->assertOk()
            ->assertSee('The answers are not available yet.')
            ->assertDontSee('Dhaka');
    });

    it('shows the options, the correct answer and the guest\'s own answer once the teacher releases them', function () {
        ($this->startAsGuest)();
        $this->post(route('guest-exam.submit', ExamAttempt::sole()), ['answers' => [$this->question->id => 'Khulna']]);
        $this->flushSession();

        $this->exam->releaseAnswers();

        ($this->findResult)('Rahim Uddin', '01712345678')
            ->assertOk()
            ->assertSee('Capital of Bangladesh?')
            ->assertSee('qb-result-option--correct', escape: false)
            ->assertSee('qb-result-option--wrong', escape: false)
            ->assertDontSee('The answers are not available yet.');
    });

    it('matches the name and contact however they are spaced or capitalised', function () {
        ($this->startAsGuest)('Rahim  Uddin', '017 1234-5678');
        $this->post(route('guest-exam.submit', ExamAttempt::sole()));

        ($this->findResult)('  rahim uddin ', '01712345678')->assertOk()->assertSee('Score');
    });

    it('does not hand a result to a wrong name or a wrong contact', function () {
        ($this->startAsGuest)();
        $this->post(route('guest-exam.submit', ExamAttempt::sole()));

        ($this->findResult)('Karim', '01712345678')->assertSessionHasErrors('result');
        ($this->findResult)('Rahim Uddin', '01999999999')->assertSessionHasErrors('result');
    });

    it('never finds a result of another exam', function () {
        ($this->startAsGuest)();
        $this->post(route('guest-exam.submit', ExamAttempt::sole()));

        $otherExam = Exam::factory()->published()->create();

        $this->post(route('guest-exam.result', $otherExam->share_token), ['guest_name' => 'Rahim Uddin', 'guest_contact' => '01712345678'])
            ->assertSessionHasErrors('result');
    });

    it('shows the first attempt when the same guest sat the exam more than once', function () {
        // The same guest can no longer start a second attempt, so two only
        // exist in records from before that rule.
        $first = ExamAttempt::factory()->submitted()->create(['exam_id' => $this->exam->id, 'is_guest' => true, 'student_id' => null, 'guest_name' => 'Rahim Uddin', 'guest_contact' => '01712345678']);
        ExamAttempt::factory()->submitted()->create(['exam_id' => $this->exam->id, 'is_guest' => true, 'student_id' => null, 'guest_name' => 'Rahim Uddin', 'guest_contact' => '01712345678']);

        expect($first->id)->toBe(ExamAttempt::oldest('id')->first()->id);
        expect(ExamAttempt::findGuestResult($this->exam, 'Rahim Uddin', '01712345678')->id)->toBe(ExamAttempt::oldest('id')->first()->id);
    });

    it('still lets a guest look their result up after the link is closed', function () {
        ($this->startAsGuest)();
        $this->post(route('guest-exam.submit', ExamAttempt::sole()));

        $this->exam->update(['is_link_active' => false]);

        $this->get(route('guest-exam.show', $this->exam->share_token))
            ->assertOk()
            ->assertSee('no longer active')
            ->assertSee('See my result');

        ($this->findResult)('Rahim Uddin', '01712345678')->assertOk()->assertSee('Score');
    });

    it('rate-limits result lookups', function () {
        foreach (range(1, 10) as $try) {
            ($this->findResult)('Rahim Uddin', "0170000000{$try}");
        }

        ($this->findResult)('Rahim Uddin', '01712345678')->assertStatus(429);
    });
});

describe('logged-in student', function () {
    beforeEach(function () {
        $this->student = User::factory()->student()->create();
        $this->actingAs($this->student);
        Filament::setCurrentPanel(Filament::getPanel('student'));

        $this->attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'is_guest' => false,
            'started_at' => now(),
        ])->fresh();
        $this->attempt->recordAnswers([$this->question->id => 'Khulna']);
        $this->attempt->submitAndAutoGrade();
    });

    it('sees only the score until the teacher releases the answers', function () {
        livewire(ExamResultPage::class, ['attempt' => $this->attempt])
            ->assertSee('The answers are not available yet.')
            ->assertDontSee('Capital of Bangladesh?')
            ->assertDontSee('Dhaka');

        $this->exam->releaseAnswers();

        livewire(ExamResultPage::class, ['attempt' => $this->attempt->fresh()])
            ->assertSee('Capital of Bangladesh?')
            ->assertSeeHtml('qb-result-option--correct')
            ->assertSeeHtml('qb-result-option--wrong');
    });

    it('lists their own submitted exams, and nobody else\'s, to come back to', function () {
        $otherStudent = User::factory()->student()->create();
        $otherExam = Exam::factory()->published()->create(['title' => 'Someone else\'s exam']);
        ExamAttempt::create(['exam_id' => $otherExam->id, 'student_id' => $otherStudent->id, 'is_guest' => false, 'started_at' => now()])
            ->fresh()
            ->submitAndAutoGrade();

        livewire(MyResults::class)
            ->assertSee('Half yearly')
            ->assertSee('Answers not released yet')
            ->assertDontSee('Someone else\'s exam');
    });

    it('always sees the answers of their own self-practice exam straight away', function () {
        $practice = Exam::factory()->selfPractice()->create(['created_by' => $this->student->id]);
        $practice->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);

        $attempt = ExamAttempt::create(['exam_id' => $practice->id, 'student_id' => $this->student->id, 'is_guest' => false, 'started_at' => now()])->fresh();
        $attempt->submitAndAutoGrade();

        livewire(ExamResultPage::class, ['attempt' => $attempt])
            ->assertSee('Capital of Bangladesh?')
            ->assertSeeHtml('qb-result-option--correct');
    });
});

describe('teacher', function () {
    beforeEach(function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
    });

    it('releases the answers and can hide them again', function () {
        livewire(ListExams::class)
            ->assertTableActionVisible('releaseAnswers', $this->exam)
            ->assertTableActionHidden('hideAnswers', $this->exam)
            ->callTableAction('releaseAnswers', $this->exam);

        expect($this->exam->fresh()->showsAnswersToStudents())->toBeTrue();

        livewire(ListExams::class)
            ->assertTableActionHidden('releaseAnswers', $this->exam)
            ->callTableAction('hideAnswers', $this->exam);

        expect($this->exam->fresh()->showsAnswersToStudents())->toBeFalse();
    });

    it('has no answers to release on an exam that is not published yet', function () {
        $draft = Exam::factory()->create(['created_by' => $this->teacher->id, 'status' => ExamStatus::Draft]);

        livewire(ListExams::class)->assertTableActionHidden('releaseAnswers', $draft);
    });
});

it('leaves the language switcher off the guest result page but keeps it on the exam link page', function () {
    ($this->startAsGuest)();
    $attempt = ExamAttempt::sole();

    $this->get(route('guest-exam.show', $this->exam->share_token))->assertSee(route('locale.switch', 'bn'));

    $this->post(route('guest-exam.submit', $attempt))->assertOk()->assertDontSee(route('locale.switch', 'bn'));

    ($this->findResult)('Rahim Uddin', '01712345678')->assertOk()->assertDontSee(route('locale.switch', 'bn'));
});

describe('scheduled answer release', function () {
    beforeEach(function () {
        $this->freezeTime();
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
    });

    it('keeps the answers locked until the scheduled time, then unlocks them by itself', function () {
        $this->exam->scheduleAnswerRelease(now()->addHours(2));

        expect($this->exam->showsAnswersToStudents())->toBeFalse();
        expect($this->exam->hasPendingAnswerRelease())->toBeTrue();

        $this->travel(2)->hours();
        $this->travel(1)->seconds();

        // Nothing ran in between — the stored time alone decides it.
        expect($this->exam->fresh()->showsAnswersToStudents())->toBeTrue();
        expect($this->exam->fresh()->hasPendingAnswerRelease())->toBeFalse();
    });

    it('shows a student the answers once the scheduled time has passed', function () {
        $student = User::factory()->student()->create();
        $attempt = ExamAttempt::create(['exam_id' => $this->exam->id, 'student_id' => $student->id, 'is_guest' => false, 'started_at' => now()])->fresh();
        $attempt->recordAnswers([$this->question->id => 'Khulna']);
        $attempt->submitAndAutoGrade();

        $this->exam->scheduleAnswerRelease(now()->addHour());

        $this->actingAs($student);
        Filament::setCurrentPanel(Filament::getPanel('student'));

        livewire(ExamResultPage::class, ['attempt' => $attempt])->assertDontSee('Capital of Bangladesh?');

        $this->travel(61)->minutes();

        livewire(ExamResultPage::class, ['attempt' => $attempt->fresh()])
            ->assertSee('Capital of Bangladesh?')
            ->assertSeeHtml('qb-result-option--correct');
    });

    it('lets the teacher set, change and cancel the release time from the exams table', function () {
        $releaseAt = now()->addDay()->startOfMinute();

        livewire(ListExams::class)
            ->callTableAction('scheduleAnswers', $this->exam, data: ['answers_release_at' => $releaseAt->toDateTimeString()])
            ->assertHasNoTableActionErrors();

        expect($this->exam->fresh()->answers_release_at->equalTo($releaseAt))->toBeTrue();
        expect($this->exam->fresh()->showsAnswersToStudents())->toBeFalse();

        livewire(ListExams::class)
            ->callTableAction('scheduleAnswers', $this->exam, data: ['answers_release_at' => null]);

        expect($this->exam->fresh()->answers_release_at)->toBeNull();
    });

    it('refuses a release time in the past', function () {
        livewire(ListExams::class)
            ->callTableAction('scheduleAnswers', $this->exam, data: ['answers_release_at' => now()->subHour()->toDateTimeString()])
            ->assertHasTableActionErrors(['answers_release_at']);

        expect($this->exam->fresh()->answers_release_at)->toBeNull();
    });

    it('still lets the teacher release by hand before the scheduled time', function () {
        $this->exam->scheduleAnswerRelease(now()->addDay());

        livewire(ListExams::class)->callTableAction('releaseAnswers', $this->exam);

        expect($this->exam->fresh()->showsAnswersToStudents())->toBeTrue();
    });

    it('locks the answers again on "hide", even after the scheduled time has passed', function () {
        $this->exam->scheduleAnswerRelease(now()->addHour());
        $this->travel(2)->hours();

        livewire(ListExams::class)
            ->assertTableActionVisible('hideAnswers', $this->exam)
            ->assertTableActionHidden('scheduleAnswers', $this->exam)
            ->callTableAction('hideAnswers', $this->exam);

        expect($this->exam->fresh()->showsAnswersToStudents())->toBeFalse();
        expect($this->exam->fresh()->answers_release_at)->toBeNull();
    });

    it('shows where the answers stand in the exams table', function () {
        $hidden = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
        $scheduled = Exam::factory()->published()->create(['created_by' => $this->teacher->id, 'answers_release_at' => now()->addDay()]);
        $released = Exam::factory()->published()->create(['created_by' => $this->teacher->id, 'answers_released_at' => now()]);

        livewire(ListExams::class)
            ->assertTableColumnStateSet('answers_release_at', 'Hidden', $hidden)
            ->assertTableColumnStateSet('answers_release_at', 'Released', $released)
            ->assertTableColumnStateSet('answers_release_at', now()->addDay()->translatedFormat('j M, g:i A'), $scheduled);
    });
});
