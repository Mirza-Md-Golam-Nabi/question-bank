<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamType;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Models\AttemptAnswer;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);

    $this->mcq = fn (array $attributes = []): Question => Question::factory()->approved()->for($this->chapter)->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        ...$attributes,
    ]);

    $this->examOf = function (array $questions, ?User $teacher = null, array $attributes = []): Exam {
        $exam = Exam::factory()->published()->create(['created_by' => ($teacher ?? $this->teacher)->id, ...$attributes]);
        $exam->questions()->attach(collect($questions)->mapWithKeys(fn (Question $question, int $index): array => [
            $question->id => ['order_index' => $index + 1, 'marks_override' => null],
        ])->all());

        return $exam;
    };

    /** `$right` participants answer the question correctly, `$wrong` wrongly. */
    $this->sitMany = function (Exam $exam, Question $question, int $right, int $wrong): void {
        foreach (range(1, $right + $wrong) as $index) {
            $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
            AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => $index <= $right ? 'right' : 'wrong']);
            $attempt->submitAndAutoGrade();
        }
    };

    $this->statsOf = fn (Question $question, ?User $teacher = null): ?array => ($row = DB::table('teacher_question_stats')
        ->where('teacher_id', ($teacher ?? $this->teacher)->id)
        ->where('question_id', $question->id)
        ->first()) ? [$row->answer_count, $row->wrong_count] : null;

    $this->picker = fn (array $overrides = []) => livewire(SelectQuestions::class)->set('data', [
        'exam_mode' => ExamDeliveryMode::Online->value,
        'academic_class_id' => $this->classSubject->academic_class_id,
        'class_subject_id' => $this->classSubject->id,
        'chapter_id' => $this->chapter->id,
        'topic_id' => null,
        'question_type' => SelectQuestions::TYPE_MCQ,
        ...$overrides,
    ]);
});

it('adds up how a teacher\'s students did on each question across that teacher\'s exams', function () {
    $question = ($this->mcq)();

    ($this->sitMany)(($this->examOf)([$question]), $question, right: 1, wrong: 2);
    ($this->sitMany)(($this->examOf)([$question]), $question, right: 2, wrong: 1);

    expect(($this->statsOf)($question))->toBe([6, 3]);
});

it('counts a blank as wrong, and guests like everyone else', function () {
    $question = ($this->mcq)();
    $attempt = ExamAttempt::factory()->guest()->create(['exam_id' => ($this->examOf)([$question])->id]);

    $attempt->submitAndAutoGrade();

    expect(($this->statsOf)($question))->toBe([1, 1]);
});

it('keeps each teacher\'s numbers apart', function () {
    $question = ($this->mcq)();

    ($this->sitMany)(($this->examOf)([$question]), $question, right: 0, wrong: 2);
    ($this->sitMany)(($this->examOf)([$question], $this->otherTeacher), $question, right: 3, wrong: 0);

    expect(($this->statsOf)($question))->toBe([2, 2])
        ->and(($this->statsOf)($question, $this->otherTeacher))->toBe([3, 0]);
});

it('does not count self-practice', function () {
    $question = ($this->mcq)();

    ($this->sitMany)(($this->examOf)([$question], attributes: ['exam_type' => ExamType::SelfPractice]), $question, right: 0, wrong: 2);

    expect(DB::table('teacher_question_stats')->count())->toBe(0);
});

it('shows the teacher how their students did on each question in the picker', function () {
    $question = ($this->mcq)();
    ($this->mcq)();
    ($this->sitMany)(($this->examOf)([$question]), $question, right: 1, wrong: 3);

    $page = ($this->picker)();

    expect($page->instance()->questions)->toHaveCount(2);
    $page->assertSee('3 of 4 wrong');
});

it('narrows the picker to the questions the teacher\'s students got wrong, the most-missed first', function () {
    $oftenWrong = ($this->mcq)();
    $sometimesWrong = ($this->mcq)();
    $neverWrong = ($this->mcq)();
    $neverSat = ($this->mcq)();
    $othersStudentsOnly = ($this->mcq)();

    ($this->sitMany)(($this->examOf)([$sometimesWrong]), $sometimesWrong, right: 3, wrong: 1);
    ($this->sitMany)(($this->examOf)([$oftenWrong]), $oftenWrong, right: 1, wrong: 3);
    ($this->sitMany)(($this->examOf)([$neverWrong]), $neverWrong, right: 2, wrong: 0);
    ($this->sitMany)(($this->examOf)([$othersStudentsOnly], $this->otherTeacher), $othersStudentsOnly, right: 0, wrong: 5);

    $page = ($this->picker)(['most_missed_only' => true]);

    expect($page->instance()->questions->pluck('id')->all())->toBe([$oftenWrong->id, $sometimesWrong->id]);
});

it('carries the numbers over to the corrected version of a question', function () {
    $original = ($this->mcq)(['created_by' => $this->teacher->id]);
    ($this->sitMany)(($this->examOf)([$original]), $original, right: 0, wrong: 2);

    $revision = $original->createRevisionWith([
        'chapter_id' => $original->chapter_id,
        'question_type' => $original->question_type,
        'question_text' => '<p>Corrected</p>',
        'options' => $original->options,
        'marks' => 1,
    ]);

    $page = ($this->picker)(['most_missed_only' => true]);

    // The teacher's own pending revision is what the picker now offers.
    expect($page->instance()->questions->pluck('id')->all())->toBe([$revision->id]);
    $page->assertSee('2 of 2 wrong');
});

it('lists the picker\'s questions in the same number of queries whatever the students\' numbers', function () {
    $queries = [];
    ($this->picker)()->html();

    foreach ([2, 12] as $questionCount) {
        $chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);
        $questions = Question::factory()->approved()->for($chapter)->count($questionCount)->create();
        $exam = ($this->examOf)($questions->all());
        ExamAttempt::factory()->create(['exam_id' => $exam->id])->submitAndAutoGrade();

        DB::flushQueryLog();
        DB::enableQueryLog();
        ($this->picker)(['chapter_id' => $chapter->id, 'most_missed_only' => true])->html();
        DB::disableQueryLog();

        $queries[$questionCount] = count(DB::getQueryLog());
    }

    expect($queries[12])->toBe($queries[2]);
});
