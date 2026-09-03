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
    $question = Question::factory()->approved()->for(Chapter::factory())->create(['correct_answer' => 'a', 'marks' => 2]);
    $exam->questions()->attach([$question->id => ['order_index' => 1, 'marks_override' => null]]);

    $startResponse = $this->post("/exam/{$exam->share_token}/start", [
        'guest_name' => 'John Guest',
    ]);
    $startResponse->assertOk();

    $attempt = ExamAttempt::first();
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
        $this->post("/exam/{$exam->share_token}/start", ['guest_name' => "Guest {$i}"]);
    }

    $this->post("/exam/{$exam->share_token}/start", ['guest_name' => 'One too many'])
        ->assertStatus(429);
});
