<?php

use App\Enums\Difficulty;
use App\Enums\ExamType;
use App\Filament\Pages\BillingSettings;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionDifficultyTracker;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();

    // Five answers are enough in these tests; the real default is thirty.
    BillingSetting::current()->fill(['difficulty_min_answers' => 5])->save();

    $this->mcq = fn (array $attributes = []): Question => Question::factory()->approved()->for($this->chapter)->create([
        'created_by' => $this->teacher->id,
        'difficulty' => Difficulty::Easy,
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        ...$attributes,
    ]);

    $this->examOf = function (array $questions, array $attributes = []): Exam {
        $exam = Exam::factory()->published()->create(['created_by' => $this->teacher->id, ...$attributes]);
        $exam->questions()->attach(collect($questions)->mapWithKeys(fn (Question $question, int $index): array => [
            $question->id => ['order_index' => $index + 1, 'marks_override' => null],
        ])->all());

        return $exam;
    };

    /**
     * One student sits the exam and hands it in; `$answers` is
     * question id => what they chose (leave a question out to skip it).
     */
    $this->sit = function (Exam $exam, array $answers): ExamAttempt {
        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);

        foreach ($answers as $questionId => $answer) {
            AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $questionId, 'student_answer' => $answer]);
        }

        $attempt->submitAndAutoGrade();

        return $attempt;
    };

    /** `$right` students answer correctly, then `$wrong` answer wrongly. */
    $this->sitMany = function (Exam $exam, Question $question, int $right, int $wrong): void {
        foreach (range(1, $right + $wrong) as $index) {
            ($this->sit)($exam, [$question->id => $index <= $right ? 'right' : 'wrong']);
        }
    };
});

it('counts every handed-in paper on a teacher exam, a blank answer as a wrong one', function () {
    $answered = ($this->mcq)();
    $skipped = ($this->mcq)();
    $exam = ($this->examOf)([$answered, $skipped]);

    ($this->sit)($exam, [$answered->id => 'right']);
    ($this->sit)($exam, [$answered->id => 'wrong']);

    expect($answered->refresh()->answer_count)->toBe(2)
        ->and($answered->correct_answer_count)->toBe(1)
        ->and($skipped->refresh()->answer_count)->toBe(2)
        ->and($skipped->correct_answer_count)->toBe(0);
});

it('keeps the difficulty the author chose until enough students have answered', function () {
    $question = ($this->mcq)(['difficulty' => Difficulty::Easy]);
    $exam = ($this->examOf)([$question]);

    ($this->sitMany)($exam, $question, right: 0, wrong: 4);

    expect($question->refresh()->difficulty)->toBe(Difficulty::Easy)
        ->and($question->hasCalculatedDifficulty())->toBeFalse();

    ($this->sit)($exam, [$question->id => 'wrong']);

    expect($question->refresh()->difficulty)->toBe(Difficulty::Hard)
        ->and($question->hasCalculatedDifficulty())->toBeTrue();
});

it('sets the difficulty from the share of correct answers', function (int $right, int $wrong, Difficulty $expected) {
    $question = ($this->mcq)(['difficulty' => $expected === Difficulty::Easy ? Difficulty::Hard : Difficulty::Easy]);

    ($this->sitMany)(($this->examOf)([$question]), $question, $right, $wrong);

    expect($question->refresh()->difficulty)->toBe($expected);
})->with([
    'nobody right is hard' => [0, 10, Difficulty::Hard],
    'exactly 33% is still hard' => [33, 67, Difficulty::Hard],
    'just over 33% is medium' => [34, 66, Difficulty::Medium],
    'exactly 66% is still medium' => [66, 34, Difficulty::Medium],
    'just over 66% is easy' => [67, 33, Difficulty::Easy],
    'everybody right is easy' => [10, 0, Difficulty::Easy],
]);

it('follows the limits the admin sets', function () {
    BillingSetting::current()->fill(['difficulty_hard_max_percent' => 50, 'difficulty_medium_max_percent' => 90])->save();
    $question = ($this->mcq)();

    ($this->sitMany)(($this->examOf)([$question]), $question, right: 4, wrong: 6);

    expect($question->refresh()->difficulty)->toBe(Difficulty::Hard);
});

it('moves the difficulty again as more students answer', function () {
    $question = ($this->mcq)();
    $exam = ($this->examOf)([$question]);

    ($this->sitMany)($exam, $question, right: 0, wrong: 5);
    expect($question->refresh()->difficulty)->toBe(Difficulty::Hard);

    ($this->sitMany)($exam, $question, right: 15, wrong: 0);
    expect($question->refresh()->difficulty)->toBe(Difficulty::Easy);
});

it('does not count self-practice', function () {
    $question = ($this->mcq)();
    $exam = ($this->examOf)([$question], ['exam_type' => ExamType::SelfPractice]);

    ($this->sitMany)($exam, $question, right: 0, wrong: 6);

    expect($question->refresh()->answer_count)->toBe(0)
        ->and($question->difficulty)->toBe(Difficulty::Easy);
});

it('never counts the same attempt twice', function () {
    $question = ($this->mcq)();
    $attempt = ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);

    $attempt->submitAndAutoGrade();
    app(QuestionDifficultyTracker::class)->recordAttempt($attempt, [$question->id]);

    expect($question->refresh()->answer_count)->toBe(1)
        ->and($question->correct_answer_count)->toBe(1);
});

it('leaves a CQ with the difficulty its author chose', function () {
    $cq = Question::factory()->cq()->approved()->for($this->chapter)->create(['difficulty' => Difficulty::Medium]);
    $mcq = ($this->mcq)();

    ($this->sitMany)(($this->examOf)([$cq, $mcq]), $mcq, right: 0, wrong: 6);

    expect($cq->refresh()->answer_count)->toBe(0)
        ->and($cq->difficulty)->toBe(Difficulty::Medium);
});

it('does not touch the question\'s updated_at when an answer is counted', function () {
    $question = ($this->mcq)();
    DB::table('questions')->where('id', $question->id)->update(['updated_at' => '2026-01-01 00:00:00']);

    ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);

    expect($question->refresh()->updated_at->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

it('carries the counts and the calculated difficulty over to the new version when a question is edited', function () {
    $question = ($this->mcq)(['difficulty' => Difficulty::Easy]);
    ($this->sitMany)(($this->examOf)([$question]), $question, right: 5, wrong: 5);
    $question->refresh();

    expect($question->difficulty)->toBe(Difficulty::Medium);

    // A spelling fix: the form no longer sends a difficulty at all.
    $revision = $question->createRevisionWith([
        'chapter_id' => $question->chapter_id,
        'question_type' => $question->question_type,
        'question_text' => '<p>Corrected spelling</p>',
        'options' => $question->options,
        'marks' => 1,
    ]);

    expect($revision->difficulty)->toBe(Difficulty::Medium)
        ->and($revision->answer_count)->toBe(10)
        ->and($revision->correct_answer_count)->toBe(5);

    // And it counts on from there.
    ($this->sitMany)(($this->examOf)([$revision]), $revision, right: 30, wrong: 0);

    expect($revision->refresh()->answer_count)->toBe(40)
        ->and($revision->difficulty)->toBe(Difficulty::Easy)
        ->and($question->refresh()->answer_count)->toBe(10);
});

it('shows the calculated difficulty on the form without letting it be changed', function () {
    $this->actingAs($this->teacher);
    $question = ($this->mcq)(['status' => 'pending']);
    ($this->sitMany)(($this->examOf)([$question]), $question, right: 2, wrong: 3);
    $exam = Exam::sole();
    $exam->attempts()->delete();

    livewire(EditQuestion::class, ['record' => $question->id])
        ->assertFormFieldDisabled('difficulty')
        ->assertFormSet(['difficulty' => Difficulty::Medium])
        ->assertSee('Set from the answers of 5 students: 40% got it right.');

    $fresh = ($this->mcq)(['status' => 'pending']);

    livewire(EditQuestion::class, ['record' => $fresh->id])->assertFormFieldEnabled('difficulty');
});

it('takes an exam\'s papers back out of the counts when the exam is cancelled', function () {
    $question = ($this->mcq)();
    $cancelled = ($this->examOf)([$question]);
    $kept = ($this->examOf)([$question]);

    ($this->sitMany)($cancelled, $question, right: 4, wrong: 2);
    ($this->sitMany)($kept, $question, right: 1, wrong: 4);

    $cancelled->cancel();

    expect($question->refresh()->answer_count)->toBe(5)
        ->and($question->correct_answer_count)->toBe(1)
        ->and($question->difficulty)->toBe(Difficulty::Hard);
});

it('keeps the counts when old attempts are simply deleted', function () {
    $question = ($this->mcq)();
    $exam = ($this->examOf)([$question]);
    ($this->sitMany)($exam, $question, right: 6, wrong: 0);

    AttemptAnswer::query()->delete();
    $exam->attempts()->delete();

    expect($question->refresh()->answer_count)->toBe(6)
        ->and($question->correct_answer_count)->toBe(6)
        ->and($question->difficulty)->toBe(Difficulty::Easy);
});

it('brings in the exams sat before the counts existed, each participant\'s first paper only', function () {
    $question = ($this->mcq)();
    $exam = ($this->examOf)([$question]);
    $student = User::factory()->student()->create();

    $paper = function (array $attempt, bool $isCorrect) use ($exam, $question): void {
        $attempt = ExamAttempt::factory()->submitted()->create(['exam_id' => $exam->id, ...$attempt]);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => 'x', 'is_correct' => $isCorrect]);
    };

    // A student's two papers and a guest's two: only the first of each counts.
    $paper(['student_id' => $student->id], false);
    $paper(['student_id' => $student->id], true);
    $paper(['student_id' => null, 'is_guest' => true, 'guest_name' => 'Rahim', 'guest_contact' => '01700000000'], true);
    $paper(['student_id' => null, 'is_guest' => true, 'guest_name' => 'Rahim', 'guest_contact' => '01700000000'], false);
    // Four more students, all wrong; and one still sitting the exam.
    foreach (range(1, 4) as $index) {
        $paper([], false);
    }
    ExamAttempt::factory()->create(['exam_id' => $exam->id]);

    $tracker = app(QuestionDifficultyTracker::class);
    $tracker->countUncountedAttempts();

    expect($question->refresh()->answer_count)->toBe(6)
        ->and($question->correct_answer_count)->toBe(1)
        ->and($question->difficulty)->toBe(Difficulty::Hard);

    // Running it again adds nothing.
    $tracker->countUncountedAttempts();

    expect($question->refresh()->answer_count)->toBe(6);
});

it('hands in a paper in the same number of queries however many questions feed the difficulty', function () {
    $queries = [];

    // The settings are read once per request, not once per paper.
    BillingSetting::current();

    foreach ([3, 30] as $questionCount) {
        $questions = collect(range(1, $questionCount))->map(fn () => ($this->mcq)());
        $exam = ($this->examOf)($questions->all());
        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        $questions->each(fn (Question $question) => AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => 'right']));
        $attempt = ExamAttempt::with('exam.questions')->find($attempt->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $attempt->submitAndAutoGrade();
        DB::disableQueryLog();

        $queries[$questionCount] = count(DB::getQueryLog());
    }

    expect($queries[30])->toBe($queries[3]);
});

it('lets the admin set the numbers of the difficulty rule, and keeps the two limits in order', function () {
    $this->actingAs(User::factory()->admin()->create());

    livewire(BillingSettings::class)
        ->assertFormSet(['difficulty_hard_max_percent' => 33, 'difficulty_medium_max_percent' => 66])
        ->fillForm(['difficulty_min_answers' => 50, 'difficulty_hard_max_percent' => 40, 'difficulty_medium_max_percent' => 70])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(BillingSetting::current()->difficulty_min_answers)->toBe(50)
        ->and(BillingSetting::current()->difficulty_hard_max_percent)->toBe(40.0)
        ->and(BillingSetting::current()->difficulty_medium_max_percent)->toBe(70.0);

    livewire(BillingSettings::class)
        ->fillForm(['difficulty_hard_max_percent' => 80, 'difficulty_medium_max_percent' => 70])
        ->call('save')
        ->assertHasFormErrors(['difficulty_hard_max_percent']);
});

it('starts out needing thirty answers, with the limits at 33% and 66%', function () {
    $defaults = new BillingSetting;

    expect($defaults->difficulty_min_answers)->toBe(30)
        ->and($defaults->difficulty_hard_max_percent)->toBe(33.0)
        ->and($defaults->difficulty_medium_max_percent)->toBe(66.0);
});
