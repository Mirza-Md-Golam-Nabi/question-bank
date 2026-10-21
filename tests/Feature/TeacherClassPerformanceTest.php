<?php

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\TeacherClassPerformance;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    BillingSetting::current()->fill(['performance_min_questions' => 1])->save();

    $this->teacher = User::factory()->teacher()->create();
    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'name' => 'Motion']);
    $this->class = app(TeacherClassPerformance::class);

    $this->mcq = fn (?Chapter $chapter = null): Question => Question::factory()->approved()->for($chapter ?? $this->chapter)->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 1,
    ]);

    $this->examOf = function (array $questions, array $attributes = []): Exam {
        $exam = Exam::factory()->published()->create([
            'created_by' => $this->teacher->id,
            'exam_type' => ExamType::TeacherExam,
            'class_subject_id' => $this->classSubject->id,
            ...$attributes,
        ]);
        $exam->questions()->attach(collect($questions)->mapWithKeys(fn (Question $question, int $index): array => [
            $question->id => ['order_index' => $index + 1, 'marks_override' => null],
        ])->all());
        $exam->recalculateTotalMarks();

        return $exam->refresh();
    };

    /** `$who` is a student, or a guest's name. */
    $this->sit = function (Exam $exam, User|string $who, array $answers): ExamAttempt {
        $this->travel(1)->minutes();
        $attempt = $who instanceof User
            ? ExamAttempt::factory()->create(['exam_id' => $exam->id, 'student_id' => $who->id])
            : ExamAttempt::factory()->guest()->create(['exam_id' => $exam->id, 'guest_name' => $who, 'guest_contact' => '01700000000']);

        foreach ($answers as $questionId => $answer) {
            AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $questionId, 'student_answer' => $answer]);
        }

        $attempt->submitAndAutoGrade();

        return $attempt;
    };

    /** A student scoring these percentages on the teacher's exams of two questions each, in order. */
    $this->scores = function (User $student, array $percents): void {
        foreach ($percents as $percent) {
            [$a, $b] = [($this->mcq)(), ($this->mcq)()];
            ($this->sit)(($this->examOf)([$a, $b]), $student, array_filter([
                $a->id => $percent >= 50 ? 'right' : 'wrong',
                $b->id => $percent === 100 ? 'right' : 'wrong',
            ]));
        }
    };
});

it('averages each exam\'s scores, guests included, oldest exam first', function () {
    $question = ($this->mcq)();
    $first = ($this->examOf)([$question], ['title' => 'First']);
    $this->travel(1)->days();
    $second = ($this->examOf)([$question], ['title' => 'Second']);

    ($this->sit)($first, User::factory()->student()->create(), [$question->id => 'right']);
    ($this->sit)($first, 'Rahim', [$question->id => 'wrong']);
    ($this->sit)($second, 'Karim', [$question->id => 'right']);

    expect($this->class->classTrend($this->teacher)->map(fn (array $exam): array => [$exam['title'], $exam['participants'], $exam['average']])->all())
        ->toBe([['First', 2, 50.0], ['Second', 1, 100.0]]);
});

it('reads only the teacher\'s own exams', function () {
    $question = ($this->mcq)();
    $student = User::factory()->student()->create();

    ($this->sit)(($this->examOf)([$question], ['created_by' => User::factory()->teacher()->create()->id]), $student, [$question->id => 'right']);
    ($this->sit)(($this->examOf)([$question], ['created_by' => $student->id, 'exam_type' => ExamType::SelfPractice]), $student, [$question->id => 'right']);

    expect($this->class->classTrend($this->teacher))->toBeEmpty()
        ->and($this->class->weakChapters($this->teacher))->toBeEmpty()
        ->and($this->class->students($this->teacher)->get())->toBeEmpty()
        ->and($this->class->hasStudent($this->teacher, $student))->toBeFalse();
});

it('finds the chapters the class answers worst, every answer counted', function () {
    $strong = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'name' => 'Light']);
    [$weakQuestion, $strongQuestion] = [($this->mcq)(), ($this->mcq)($strong)];
    $exam = ($this->examOf)([$weakQuestion, $strongQuestion]);

    ($this->sit)($exam, 'Rahim', [$weakQuestion->id => 'wrong', $strongQuestion->id => 'right']);
    ($this->sit)($exam, User::factory()->student()->create(), [$strongQuestion->id => 'right']);

    $chapters = $this->class->weakChapters($this->teacher);

    expect($chapters->map(fn (array $row): array => [$row['chapter']->name, $row['rate']->percent(), $row['rate']->blank])->all())
        ->toBe([['Motion', 0, 1], ['Light', 100, 0]]);
});

it('lists the logged-in students with their exams and average, never a guest', function () {
    $student = User::factory()->student()->create();
    $question = ($this->mcq)();

    ($this->sit)(($this->examOf)([$question]), $student, [$question->id => 'right']);
    ($this->sit)(($this->examOf)([$question]), $student, [$question->id => 'wrong']);
    ($this->sit)(($this->examOf)([$question]), 'Guest', [$question->id => 'right']);

    $listed = $this->class->students($this->teacher)->get();

    expect($listed->pluck('id')->all())->toBe([$student->id])
        ->and((int) $listed->first()->exams_count)->toBe(2)
        ->and(round((float) $listed->first()->average_score))->toBe(50.0)
        ->and($this->class->hasStudent($this->teacher, $student))->toBeTrue();
});

it('narrows to one class and subject', function () {
    $other = ClassSubject::factory()->create();
    $question = ($this->mcq)();

    ($this->sit)(($this->examOf)([$question], ['title' => 'Other', 'class_subject_id' => $other->id]), 'Guest', [$question->id => 'right']);
    ($this->sit)(($this->examOf)([$question], ['title' => 'This']), 'Guest', [$question->id => 'right']);

    expect($this->class->classTrend($this->teacher, $this->classSubject->id)->pluck('title')->all())->toBe(['This']);
});

it('says whether each student went up or down on their latest exam', function () {
    [$up, $down, $once] = User::factory()->student()->count(3)->create()->all();

    ($this->scores)($up, [0, 50]);
    ($this->scores)($down, [100, 50]);
    ($this->scores)($once, [50]);

    expect($this->class->directions($this->teacher, [$up->id, $down->id, $once->id]))
        ->toBe([$up->id => 1, $down->id => -1, $once->id => 0]);
});

it('points out a student whose score has fallen as many exams in a row as the Admin says', function () {
    BillingSetting::current()->fill(['performance_falling_exams' => 2])->save();
    [$falling, $notQuite] = User::factory()->student()->count(2)->create()->all();

    ($this->scores)($falling, [100, 50, 0]);
    ($this->scores)($notQuite, [50, 100, 0]);

    $attention = $this->class->needingAttention($this->teacher);

    expect($attention->pluck('student.id')->all())->toBe([$falling->id])
        ->and($attention->first())->toMatchArray(['falling' => true, 'inactive' => false]);
});

it('points out a student who has stopped sitting the teacher\'s exams', function () {
    BillingSetting::current()->fill(['performance_inactive_days' => 14])->save();
    [$away, $present] = User::factory()->student()->count(2)->create()->all();
    $question = ($this->mcq)();

    ($this->sit)(($this->examOf)([$question]), $away, [$question->id => 'right']);
    $this->travel(20)->days();

    // Nobody has been given an exam since, so nobody has missed one.
    expect($this->class->needingAttention($this->teacher))->toBeEmpty();

    ($this->sit)(($this->examOf)([$question]), $present, [$question->id => 'right']);
    ($this->examOf)([$question], ['status' => ExamStatus::Draft]);

    expect($this->class->needingAttention($this->teacher)->map(fn (array $row): array => [$row['student']->id, $row['inactive']])->all())
        ->toBe([[$away->id, true]]);
});
