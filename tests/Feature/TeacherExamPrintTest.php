<?php

use App\Enums\ExamDeliveryMode;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
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
