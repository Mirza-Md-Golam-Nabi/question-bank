<?php

use App\Enums\ExamAttemptStatus;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('auto-grades mcq answers and sums the correct ones into total_score', function () {
    $exam = Exam::factory()->create();
    $correctQuestion = Question::factory()->for(Chapter::factory())->approved()->create([
        'options' => [
            ['option' => 'a', 'image' => null, 'is_correct' => true],
            ['option' => 'b', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 2,
    ]);
    $wrongQuestion = Question::factory()->for(Chapter::factory())->approved()->create([
        'options' => [
            ['option' => 'a', 'image' => null, 'is_correct' => false],
            ['option' => 'b', 'image' => null, 'is_correct' => true],
        ],
        'marks' => 3,
    ]);

    $exam->questions()->attach([
        $correctQuestion->id => ['order_index' => 1, 'marks_override' => null],
        $wrongQuestion->id => ['order_index' => 2, 'marks_override' => null],
    ]);

    $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
    $attempt->answers()->create(['question_id' => $correctQuestion->id, 'student_answer' => 'a']);
    $attempt->answers()->create(['question_id' => $wrongQuestion->id, 'student_answer' => 'a']);

    $attempt->submitAndAutoGrade();

    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect((float) $attempt->total_score)->toBe(2.0);
    expect($attempt->answers()->where('question_id', $correctQuestion->id)->first()->is_correct)->toBeTrue();
    expect($attempt->answers()->where('question_id', $wrongQuestion->id)->first()->is_correct)->toBeFalse();
});

it('respects a marks_override when auto-grading', function () {
    $exam = Exam::factory()->create();
    $question = Question::factory()->for(Chapter::factory())->approved()->create([
        'options' => [
            ['option' => 'a', 'image' => null, 'is_correct' => true],
            ['option' => 'b', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 2,
    ]);

    $exam->questions()->attach([$question->id => ['order_index' => 1, 'marks_override' => 10]]);

    $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
    $attempt->answers()->create(['question_id' => $question->id, 'student_answer' => 'a']);

    $attempt->submitAndAutoGrade();

    expect((float) $attempt->total_score)->toBe(10.0);
});

it('leaves cq answers ungraded for manual grading', function () {
    $exam = Exam::factory()->create();
    $cqQuestion = Question::factory()->cq()->for(Chapter::factory())->approved()->create();

    $exam->questions()->attach([$cqQuestion->id => ['order_index' => 1, 'marks_override' => null]]);

    $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
    $attempt->answers()->create(['question_id' => $cqQuestion->id, 'student_answer' => 'My essay answer']);

    $attempt->submitAndAutoGrade();

    $answer = $attempt->answers()->first();
    expect($answer->is_correct)->toBeNull();
    expect((float) $answer->obtained_marks)->toBe(0.0);
});

describe('per-attempt shuffling', function () {
    beforeEach(function () {
        $this->exam = Exam::factory()->published()->create();
        $questions = Question::factory()->for(Chapter::factory())->approved()->count(12)->create();

        $this->exam->questions()->attach(
            $questions->mapWithKeys(fn (Question $question, int $index) => [
                $question->id => ['order_index' => $index + 1, 'marks_override' => null],
            ])->all(),
        );

        $this->originalOrder = $questions->pluck('id')->all();
        $this->attempt = fn (): ExamAttempt => ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'is_guest' => true,
            'guest_name' => fake()->name(),
            'started_at' => now(),
        ]);
    });

    it('gives each attempt its own question order, without losing or repeating a question', function () {
        $orders = collect(range(1, 5))->map(fn () => ($this->attempt)()->shuffledQuestions()->pluck('id')->all());

        $orders->each(fn (array $order) => expect(collect($order)->sort()->values()->all())->toBe($this->originalOrder));

        expect($orders->map(fn (array $order) => implode(',', $order))->unique()->count())->toBeGreaterThan(1);
    });

    it('keeps the same order every time the same attempt is shown', function () {
        $attempt = ($this->attempt)();
        $reloaded = ExamAttempt::find($attempt->id);

        expect($reloaded->shuffledQuestions()->pluck('id')->all())->toBe($attempt->shuffledQuestions()->pluck('id')->all());
    });

    it('still grades an attempt whose questions were shuffled', function () {
        $attempt = ($this->attempt)();
        $question = $attempt->shuffledQuestions()->first();
        $correctOption = collect($question->options)->firstWhere('is_correct', true);

        $attempt->answers()->create(['question_id' => $question->id, 'student_answer' => $correctOption['option']]);
        $attempt->submitAndAutoGrade();

        expect((float) $attempt->fresh()->total_score)->toBe((float) $question->marks);
    });
});

describe('exam time limit', function () {
    beforeEach(function () {
        $this->freezeTime();

        $this->exam = Exam::factory()->published()->create(['duration_minutes' => 10]);
        $this->question = Question::factory()->for(Chapter::factory())->approved()->create([
            'options' => [
                ['option' => 'right', 'image' => null, 'is_correct' => true],
                ['option' => 'wrong', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $this->exam->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);

        $this->attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'is_guest' => true,
            'guest_name' => 'Rahim',
            'started_at' => now(),
        ])->fresh();
    });

    it('counts down from the exam duration and stops at zero', function () {
        expect($this->attempt->secondsRemaining())->toBe(600);

        $this->travel(4)->minutes();
        expect($this->attempt->secondsRemaining())->toBe(360);

        $this->travel(1)->hours();
        expect($this->attempt->secondsRemaining())->toBe(0);
    });

    it('records answers given within the time', function () {
        $this->travel(9)->minutes();

        $this->attempt->recordAnswers([$this->question->id => 'right']);

        expect($this->attempt->answers()->sole()->student_answer)->toBe('right');
    });

    it('ignores answers changed after the time ran out, keeping what was saved in time', function () {
        $this->attempt->recordAnswers([$this->question->id => 'wrong']);

        $this->travel(11)->minutes();
        $this->attempt->recordAnswers([$this->question->id => 'right']);

        expect($this->attempt->answers()->sole()->student_answer)->toBe('wrong');
    });

    it('still takes a late submission when nothing at all was saved in time', function () {
        $this->travel(11)->minutes();

        $this->attempt->recordAnswers([$this->question->id => 'right']);

        expect($this->attempt->answers()->sole()->student_answer)->toBe('right');
    });

    it('never records an answer for a question that is not part of the exam', function () {
        $foreign = Question::factory()->for(Chapter::factory())->approved()->create();

        $this->attempt->recordAnswers([$foreign->id => 'anything', 'junk' => 'x', $this->question->id => ['array']]);

        expect($this->attempt->answers()->count())->toBe(0);
    });

    it('lets a guest page save its answers when the clock runs out, then submit later without changing them', function () {
        $this->travel(10)->minutes();

        $this->post(route('guest-exam.answers', $this->attempt), ['answers' => [$this->question->id => 'wrong']])
            ->assertNoContent();

        $this->travel(5)->minutes();

        $this->post(route('guest-exam.submit', $this->attempt), ['answers' => [$this->question->id => 'right']])
            ->assertOk();

        expect($this->attempt->answers()->sole()->student_answer)->toBe('wrong');
        expect((float) $this->attempt->fresh()->total_score)->toBe(0.0);
    });

    it('shows the countdown on the guest exam page', function () {
        $this->followingRedirects()
            ->post(route('guest-exam.start', $this->exam->share_token), ['guest_name' => 'Karim', 'guest_contact' => '01700000001'])
            ->assertOk()
            ->assertSee('data-seconds="600"', escape: false)
            ->assertSee('data-qb-answers', escape: false);
    });
});
