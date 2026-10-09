<?php

use App\Enums\ExamType;
use App\Enums\StaffEarningStatus;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionRate;
use App\Models\StaffEarning;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * The figures the database now works out itself (rather than PHP adding up
 * rows it loaded) — checked against known values.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->earning = function (User $staff, float $amount, array $attributes = [], ?Chapter $chapter = null): StaffEarning {
        return StaffEarning::factory()->create([
            'staff_id' => $staff->id,
            'question_id' => Question::factory()->approved()->for($chapter ?? Chapter::factory()),
            'amount' => $amount,
            ...$attributes,
        ]);
    };
});

it('adds up a staff member\'s earnings: count, earned, paid, unpaid and this month', function () {
    $this->travelTo('2026-06-15 12:00:00');
    $staff = User::factory()->staff()->create();
    ($this->earning)($staff, 10, ['created_at' => '2026-06-01 00:00:00']);
    ($this->earning)($staff, 20, ['created_at' => '2026-06-30 23:59:59', 'status' => StaffEarningStatus::Paid]);
    ($this->earning)($staff, 40, ['created_at' => '2026-05-31 23:59:59']);
    ($this->earning)(User::factory()->staff()->create(), 500, ['created_at' => '2026-06-10 00:00:00']);

    expect(StaffEarning::totalsFor($staff->id))->toBe([
        'count' => 3,
        'earned' => 70.0,
        'paid' => 20.0,
        'unpaid' => 50.0,
        'this_month' => 30.0,
    ]);
});

it('reports zeros for a staff member with no earnings', function () {
    expect(StaffEarning::totalsFor(User::factory()->staff()->create()->id))
        ->toBe(['count' => 0, 'earned' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0, 'this_month' => 0.0]);
});

it('totals earnings per month, this month first, with empty months as zero', function () {
    $this->travelTo('2026-06-15 12:00:00');
    $staff = User::factory()->staff()->create();
    ($this->earning)($staff, 10, ['created_at' => '2026-06-02 10:00:00']);
    ($this->earning)($staff, 5, ['created_at' => '2026-06-20 10:00:00']);
    ($this->earning)($staff, 40, ['created_at' => '2026-04-30 23:00:00']);
    // Older than the three months asked for.
    ($this->earning)($staff, 999, ['created_at' => '2026-03-31 23:00:00']);

    expect(StaffEarning::monthlyTotalsFor($staff->id, months: 3)->all())->toBe([
        ['label' => "Jun '26", 'total' => 15.0],
        ['label' => "May '26", 'total' => 0.0],
        ['label' => "Apr '26", 'total' => 40.0],
    ]);
});

it('breaks earnings down per class and subject, keeping one subject in two classes apart', function () {
    $staff = User::factory()->staff()->create();
    $physics = Subject::factory()->create(['name' => 'Physics']);
    $nine = ClassSubject::factory()->create(['subject_id' => $physics->id]);
    $ten = ClassSubject::factory()->create(['subject_id' => $physics->id]);
    $chapterNine = Chapter::factory()->create(['class_subject_id' => $nine->id]);
    $chapterTen = Chapter::factory()->create(['class_subject_id' => $ten->id]);
    ($this->earning)($staff, 10, chapter: $chapterNine);
    ($this->earning)($staff, 10, chapter: $chapterNine);
    ($this->earning)($staff, 7, chapter: $chapterTen);

    $rows = StaffEarning::byClassSubjectFor($staff->id);

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('subject')->unique()->all())->toBe(['Physics']);
    expect($rows->sortBy('count')->values()->map(fn (array $row) => [$row['count'], $row['total']])->all())
        ->toBe([[1, 7.0], [2, 20.0]]);
});

it('gives each subject its own rate and the default rate to a subject without one', function () {
    $withRate = Subject::factory()->create();
    $withoutRate = Subject::factory()->create();
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 5, 'effective_from' => now()->subYear()]);
    QuestionRate::factory()->create(['subject_id' => $withRate->id, 'rate_amount' => 8, 'effective_from' => now()->subMonths(2)]);
    QuestionRate::factory()->create(['subject_id' => $withRate->id, 'rate_amount' => 12, 'effective_from' => now()->subMonth()]);
    // Not in effect yet.
    QuestionRate::factory()->create(['subject_id' => $withRate->id, 'rate_amount' => 99, 'effective_from' => now()->addMonth()]);

    expect(QuestionRate::ratesFor([$withRate->id, $withoutRate->id]))
        ->toBe([$withRate->id => 12.0, $withoutRate->id => 5.0]);
    expect(QuestionRate::rateFor(null))->toBe(5.0);
});

it('counts only the exams made inside the month, to the first and last second of it', function () {
    $teacher = User::factory()->teacher()->create();
    $examMadeAt = fn (string $createdAt) => Exam::factory()->create(['created_by' => $teacher->id, 'created_at' => $createdAt]);
    $examMadeAt('2026-05-31 23:59:59');
    $examMadeAt('2026-06-01 00:00:00');
    $examMadeAt('2026-06-30 23:59:59');
    $examMadeAt('2026-07-01 00:00:00');

    $this->travelTo('2026-06-15 12:00:00');

    expect(Exam::createdThisMonthBy($teacher, ExamType::TeacherExam)->count())->toBe(2);
});

it('offers only matching approved questions of the subject as select options, a limited number at a time', function () {
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $match = Question::factory()->approved()->for($chapter)->create(['question_text' => '<p>What is <b>photosynthesis</b>?</p>']);
    Question::factory()->approved()->for($chapter)->create(['question_text' => '<p>What is gravity?</p>']);
    Question::factory()->for($chapter)->create(['question_text' => '<p>Pending photosynthesis question</p>']);
    Question::factory()->approved()->for(Chapter::factory())->create(['question_text' => '<p>photosynthesis in another subject</p>']);

    expect(Question::approvedOptionsForSubject($classSubject->subject_id, 'photosynthesis'))
        ->toBe([$match->id => 'What is photosynthesis?']);
});

it('never offers more than the select limit, however large the pool', function () {
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    foreach (range(1, Question::SELECT_OPTIONS_LIMIT + 5) as $index) {
        // The factory draws each option from a small pool of unique words.
        fake()->unique(true);
        Question::factory()->approved()->for($chapter)->create();
    }

    expect(Question::approvedOptionsForSubject($classSubject->subject_id))->toHaveCount(Question::SELECT_OPTIONS_LIMIT);
});

it('treats % typed into the question search as plain text', function () {
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    Question::factory()->approved()->for($chapter)->create(['question_text' => '<p>plain question</p>']);
    $withPercent = Question::factory()->approved()->for($chapter)->create(['question_text' => '<p>What is 50% of 10?</p>']);

    expect(array_keys(Question::approvedOptionsForSubject($classSubject->subject_id, '%')))->toBe([$withPercent->id]);
});

it('labels only chosen questions that are in the approved pool', function () {
    $approved = Question::factory()->approved()->for(Chapter::factory())->create(['question_text' => '<p>Approved one</p>']);
    $pending = Question::factory()->for(Chapter::factory())->create();

    expect(Question::approvedOptionLabels([$approved->id, $pending->id]))->toBe([$approved->id => 'Approved one']);
});
