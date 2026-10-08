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

it('shows the join page for an active shared exam', function () {
    $exam = Exam::factory()->published()->create();

    $this->get("/exam/{$exam->share_token}")
        ->assertOk()
        ->assertSee($exam->title);
});

it('returns the inactive page for an expired or deactivated link', function () {
    $exam = Exam::factory()->published()->create(['is_link_active' => false]);

    $this->get("/exam/{$exam->share_token}")
        ->assertOk()
        ->assertSee('no longer active');
});

it('returns the inactive page for an unknown share token', function () {
    $this->get('/exam/does-not-exist')
        ->assertOk()
        ->assertSee('no longer active');
});

it('lets a guest start and submit an attempt without logging in', function () {
    $exam = Exam::factory()->published()->create();
    $question = Question::factory()->approved()->for(Chapter::factory())->create([
        'options' => [
            ['option' => 'a', 'image' => null, 'is_correct' => true],
            ['option' => 'b', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 2,
    ]);
    $exam->questions()->attach([$question->id => ['order_index' => 1, 'marks_override' => null]]);

    $startResponse = $this->post("/exam/{$exam->share_token}/start", [
        'guest_name' => 'John Guest',
        'guest_contact' => '01700000001',
    ]);

    $attempt = ExamAttempt::first();
    $startResponse->assertRedirect(route('guest-exam.take', $attempt));
    $this->get(route('guest-exam.take', $attempt))->assertOk();
    expect($attempt->is_guest)->toBeTrue();
    expect($attempt->guest_name)->toBe('John Guest');
    expect($attempt->student_id)->toBeNull();

    $submitResponse = $this->post("/exam/attempt/{$attempt->id}/submit", [
        'answers' => [$question->id => 'a'],
    ]);
    $submitResponse->assertOk();

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect((float) $attempt->total_score)->toBe(2.0);
});

it('rate-limits guest submissions', function () {
    $exam = Exam::factory()->published()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post("/exam/{$exam->share_token}/start", ['guest_name' => "Guest {$i}", 'guest_contact' => '01700000001']);
        // Each guest is a different browser; the same one would just resume.
        $this->flushSession();
    }

    $this->post("/exam/{$exam->share_token}/start", ['guest_name' => 'One too many', 'guest_contact' => '01700000001'])
        ->assertStatus(429);
});

it('shows every option on the result, marking the correct one and the wrong pick, once the answers are released', function () {
    $exam = Exam::factory()->published()->create();
    $exam->releaseAnswers();
    $options = fn (string $prefix): array => [
        ['option' => "{$prefix}-right", 'image' => null, 'is_correct' => true],
        ['option' => "{$prefix}-wrong", 'image' => null, 'is_correct' => false],
        ['option' => "{$prefix}-other", 'image' => null, 'is_correct' => false],
    ];

    $answeredRight = Question::factory()->approved()->for(Chapter::factory())->create(['options' => $options('first')]);
    $answeredWrong = Question::factory()->approved()->for(Chapter::factory())->create(['options' => $options('second')]);
    $unanswered = Question::factory()->approved()->for(Chapter::factory())->create(['options' => $options('third')]);

    $exam->questions()->attach([
        $answeredRight->id => ['order_index' => 1, 'marks_override' => null],
        $answeredWrong->id => ['order_index' => 2, 'marks_override' => null],
        $unanswered->id => ['order_index' => 3, 'marks_override' => null],
    ]);

    $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'is_guest' => true, 'guest_name' => 'Rahim', 'started_at' => now()]);

    $html = $this->post(route('guest-exam.submit', $attempt), [
        'answers' => [$answeredRight->id => 'first-right', $answeredWrong->id => 'second-wrong'],
    ])->assertOk()->getContent();

    $classesOf = function (string $optionText) use ($html): string {
        preg_match('/<li class="([^"]*)">(?:(?!<\/li>).)*?'.preg_quote($optionText, '/').'/s', $html, $matches);

        return $matches[1] ?? '';
    };

    // Every option is listed, including those of the unanswered question.
    expect($html)->toContain('first-other', 'second-other', 'third-wrong', 'Not answered');

    expect($classesOf('first-right'))->toContain('qb-result-option--correct');
    expect($classesOf('second-right'))->toContain('qb-result-option--correct');
    expect($classesOf('third-right'))->toContain('qb-result-option--correct');

    expect($classesOf('second-wrong'))->toContain('qb-result-option--wrong');
    expect($classesOf('first-wrong'))->not->toContain('qb-result-option--');
    expect($classesOf('third-wrong'))->not->toContain('qb-result-option--');
});

it('closes a guest\'s result back to the home page', function () {
    $attempt = ExamAttempt::create([
        'exam_id' => Exam::factory()->published()->create()->id,
        'is_guest' => true,
        'guest_name' => 'Rahim',
        'started_at' => now(),
    ]);

    $this->post(route('guest-exam.submit', $attempt))
        ->assertOk()
        ->assertSee('href="'.route('home').'"', escape: false);
});

describe('refreshing the guest exam page', function () {
    beforeEach(function () {
        $this->freezeTime();
        $this->exam = Exam::factory()->published()->create(['duration_minutes' => 10]);
        $this->start = fn () => $this->post(route('guest-exam.start', $this->exam->share_token), ['guest_name' => 'Rahim', 'guest_contact' => '01700000001']);
    });

    it('keeps the same attempt and the running clock when the page is refreshed', function () {
        ($this->start)();
        $attempt = ExamAttempt::sole();

        $this->travel(4)->minutes();

        $this->get(route('guest-exam.take', $attempt))
            ->assertOk()
            ->assertSee('data-seconds="360"', escape: false);

        expect(ExamAttempt::count())->toBe(1);
    });

    it('resumes the attempt under way instead of starting a new one', function () {
        ($this->start)();
        $attempt = ExamAttempt::sole();

        $this->travel(4)->minutes();

        ($this->start)()->assertRedirect(route('guest-exam.take', $attempt));

        expect(ExamAttempt::count())->toBe(1);
    });

    it('starts a fresh attempt once the previous one was submitted', function () {
        ($this->start)();
        $this->post(route('guest-exam.submit', ExamAttempt::sole()));

        ($this->start)();

        expect(ExamAttempt::count())->toBe(2);
    });

    it('does not open an attempt that was started in another browser', function () {
        ($this->start)();
        $attempt = ExamAttempt::sole();

        $this->flushSession();

        $this->get(route('guest-exam.take', $attempt))->assertForbidden();
    });

    it('sends a guest back to the exam link when their attempt is already submitted', function () {
        ($this->start)();
        $attempt = ExamAttempt::sole();
        $this->post(route('guest-exam.submit', $attempt));

        $this->get(route('guest-exam.take', $attempt))->assertRedirect(route('guest-exam.show', $this->exam->share_token));
    });

    it('shows the answers already on record again after a refresh', function () {
        $question = Question::factory()->approved()->for(Chapter::factory())->create([
            'options' => [
                ['option' => 'first', 'image' => null, 'is_correct' => true],
                ['option' => 'second', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $this->exam->questions()->attach($question->id, ['order_index' => 1, 'marks_override' => null]);

        ($this->start)();
        $attempt = ExamAttempt::sole();
        $attempt->recordAnswers([$question->id => 'second']);

        $html = $this->get(route('guest-exam.take', $attempt))->assertOk()->getContent();

        expect($html)->toMatch('/value="second"\s+checked/');
        expect($html)->not->toMatch('/value="first"\s+checked/');
    });
});
