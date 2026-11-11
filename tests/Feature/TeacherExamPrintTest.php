<?php

use App\Enums\ExamDeliveryMode;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\BoardMcqQuestion;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->subscription = subscribeToEverything($this->teacher);
    $this->actingAs($this->teacher);

    $this->examWith = function (ExamDeliveryMode $mode): Exam {
        $exam = Exam::factory()->delivery($mode)->create(['created_by' => $this->teacher->id, 'title' => 'Half yearly']);
        $chapter = Chapter::factory()->create();

        $mcq = Question::factory()->approved()->for($chapter)->create([
            'question_text' => '<p>Capital of Bangladesh?</p>',
            'options' => [
                ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
                ['option' => 'Khulna', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $cq = Question::factory()->cq()->approved()->for($chapter)->create(['question_text' => '<p>Read the stimulus.</p>']);
        $cq->cqParts()->create(['part_type' => 'knowledge', 'part_order' => 1, 'part_text' => '<p>What is a cell?</p>', 'marks' => 4]);

        $exam->questions()->attach([
            $mcq->id => ['order_index' => 1, 'marks_override' => null],
            $cq->id => ['order_index' => 2, 'marks_override' => null],
        ]);

        return $exam;
    };
});

it('prints every question of an offline exam without marking the answers', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);

    $this->get(route('filament.teacher.exams.print', $exam))
        ->assertOk()
        ->assertSee('Half yearly')
        ->assertSee('Capital of Bangladesh?')
        ->assertSee('Read the stimulus.')
        ->assertSee('What is a cell?')
        ->assertDontSee('class="qb-question-option qb-question-option--correct"', escape: false);
});

it('marks the correct mcq option on the answer copy', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Both);

    $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 1]))
        ->assertOk()
        ->assertSee('class="qb-question-option qb-question-option--correct"', escape: false);
});

it('prints the answer key alone: each mcq number and its right option\'s letter', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);
    $second = Question::factory()->approved()->create([
        'options' => [
            ['option' => 'Rice', 'image' => null, 'is_correct' => false],
            ['option' => 'Hilsa', 'image' => null, 'is_correct' => true],
        ],
    ]);
    $exam->questions()->attach($second->id, ['order_index' => 3, 'marks_override' => null]);

    $html = $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 'key']))
        ->assertOk()
        ->assertSee('Answer key')
        ->assertDontSee('Capital of Bangladesh?')
        ->assertDontSee('What is a cell?')
        ->getContent();

    preg_match_all('/qb-answer-key-number">(\d*)<.*?qb-answer-key-answer">([^<]*)</s', $html, $cells, PREG_SET_ORDER);

    expect(collect($cells)->filter(fn (array $cell): bool => $cell[1] !== '')->mapWithKeys(fn (array $cell): array => [$cell[1] => $cell[2]])->all())
        ->toBe([1 => 'A', 2 => 'B']);
});

it('offers no answer key for a paper with no mcq', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);
    $exam->questions()->where('question_type', 'mcq')->get()->each(fn (Question $question) => $exam->questions()->detach($question->id));

    $this->get(route('filament.teacher.exams.print', $exam))->assertOk()->assertDontSee('Answer key only');
    $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 'key']))->assertNotFound();
});

it('offers "print answer key only" in the exam list only for a printed paper with mcq', function () {
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $offline = ($this->examWith)(ExamDeliveryMode::Offline);
    $online = ($this->examWith)(ExamDeliveryMode::Online);
    $cqOnly = ($this->examWith)(ExamDeliveryMode::Offline);
    $cqOnly->questions()->where('question_type', 'mcq')->get()->each(fn (Question $question) => $cqOnly->questions()->detach($question->id));

    livewire(ListExams::class)
        ->assertTableActionVisible('printAnswerKey', $offline)
        ->assertTableActionHasUrl('printAnswerKey', route('filament.teacher.exams.print', ['exam' => $offline, 'answers' => 'key']), $offline)
        ->assertTableActionHidden('printAnswerKey', $online)
        ->assertTableActionHidden('printAnswerKey', $cqOnly);
});

it('lists a past paper\'s several right options, or a dash when it gives none', function () {
    $several = BoardMcqQuestion::factory()->make(['options' => [
        ['option' => 'a', 'image' => null, 'is_correct' => true],
        ['option' => 'b', 'image' => null, 'is_correct' => false],
        ['option' => 'c', 'image' => null, 'is_correct' => true],
    ]]);
    $none = BoardMcqQuestion::factory()->make(['options' => [
        ['option' => 'a', 'image' => null, 'is_correct' => false],
        ['option' => 'b', 'image' => null, 'is_correct' => false],
    ]]);

    $html = view('teacher.partials.exam-answer-key', ['mcqQuestions' => collect([$several, $none])])->render();

    expect($html)->toContain('qb-answer-key-answer">A, C<')
        ->toContain('qb-answer-key-answer">—<')
        ->toContain('this question has no correct answer');
});

it('has no printable paper for an online-only exam', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Online);

    $this->get(route('filament.teacher.exams.print', $exam))->assertNotFound();
});

it('does not let another teacher print the exam', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);

    $this->actingAs(User::factory()->teacher()->create());

    $this->get(route('filament.teacher.exams.print', $exam))->assertForbidden();
});

it('leaves cq questions out of the online attempt of an online + offline exam', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Both);
    $exam->recalculateTotalMarks();
    $exam->publish();

    auth()->logout();

    $this->followingRedirects()
        ->post(route('guest-exam.start', $exam->share_token), ['guest_name' => 'Rahim', 'guest_contact' => '01700000001'])
        ->assertOk()
        ->assertSee('Capital of Bangladesh?')
        ->assertDontSee('Read the stimulus.');

    // Scored out of the MCQ marks only, since that is all a student sees.
    expect((float) $exam->fresh()->total_marks)->toBe(1.0);
});
