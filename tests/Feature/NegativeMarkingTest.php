<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\QuestionType;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    /** An MCQ worth `$marks` whose right answer is "a". */
    $this->mcq = fn (float $marks = 1): Question => Question::factory()->for(Chapter::factory())->approved()->create([
        'options' => [
            ['option' => 'a', 'image' => null, 'is_correct' => true],
            ['option' => 'b', 'image' => null, 'is_correct' => false],
        ],
        'marks' => $marks,
    ]);

    /** Sits the exam with the given answers (null = left blank) and returns the graded attempt. */
    $this->sit = function (Exam $exam, array $answers): ExamAttempt {
        $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'student_id' => User::factory()->student()->create()->id, 'started_at' => now()]);

        foreach ($answers as $questionId => $answer) {
            AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $questionId, 'student_answer' => $answer]);
        }

        $attempt->submitAndAutoGrade();

        return $attempt->refresh();
    };
});

it('takes the set marks off each wrong answer, nothing off a blank one', function () {
    $exam = Exam::factory()->published()->create(['negative_marks' => 0.25]);
    [$right, $wrong, $blank] = [($this->mcq)(2), ($this->mcq)(2), ($this->mcq)(2)];
    $exam->questions()->attach([
        $right->id => ['order_index' => 1, 'marks_override' => null],
        $wrong->id => ['order_index' => 2, 'marks_override' => null],
        $blank->id => ['order_index' => 3, 'marks_override' => null],
    ]);

    $attempt = ($this->sit)($exam, [$right->id => 'a', $wrong->id => 'b', $blank->id => null]);

    expect($attempt->total_score)->toEqual(1.75)
        ->and($attempt->answers->firstWhere('question_id', $wrong->id)->obtained_marks)->toEqual(-0.25)
        ->and($attempt->answers->firstWhere('question_id', $blank->id)->obtained_marks)->toEqual(0);
});

it('never takes the total below zero', function () {
    $exam = Exam::factory()->published()->create(['negative_marks' => 1]);
    $wrong = ($this->mcq)();
    $exam->questions()->attach([$wrong->id => ['order_index' => 1, 'marks_override' => null]]);

    expect(($this->sit)($exam, [$wrong->id => 'b'])->total_score)->toEqual(0);
});

it('takes nothing off without negative marking', function () {
    $exam = Exam::factory()->published()->create();
    $wrong = ($this->mcq)();
    $exam->questions()->attach([$wrong->id => ['order_index' => 1, 'marks_override' => null]]);

    expect(($this->sit)($exam, [$wrong->id => 'b'])->answers->first()->obtained_marks)->toEqual(0);
});

it('lets the teacher turn it on when saving an online exam, starting on the admin\'s amount, and tells the students', function () {
    BillingSetting::create(['default_negative_marks' => 0.5]);
    $teacher = User::factory()->teacher()->create();
    subscribeToEverything($teacher);
    $this->actingAs($teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));
    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $question = Question::factory()->approved()->for($chapter)->create(['question_type' => QuestionType::Mcq]);

    livewire(SelectQuestions::class)
        ->set('data', [
            'exam_mode' => ExamDeliveryMode::Online->value,
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $chapter->id,
            'topic_id' => null,
            'question_type' => SelectQuestions::TYPE_MCQ,
        ])
        ->mountAction('saveExam', arguments: ['ids' => [$question->id]])
        ->assertSchemaStateSet(['negative_marking' => false, 'negative_marks' => 0.5], 'mountedActionSchema0')
        ->setActionData(['title' => 'Quiz', 'duration_minutes' => 20, 'negative_marking' => true, 'negative_marks' => 0.25])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $exam = Exam::sole();
    expect($exam->negative_marks)->toEqual(0.25);

    // The paper says so above the questions, before the first answer.
    expect(Blade::render('<x-exam-heading :exam="$exam" />', ['exam' => $exam]))
        ->toContain('0.25 taken off for each wrong answer');
});

it('keeps negative marking off a print-only exam', function () {
    $teacher = User::factory()->teacher()->create();
    subscribeToEverything($teacher);
    $classSubject = ClassSubject::factory()->create();
    $question = Question::factory()->approved()->for(Chapter::factory()->create(['class_subject_id' => $classSubject->id]))->create();

    $exam = app(TeacherExamBuilder::class)
        ->build($teacher, $classSubject, ExamDeliveryMode::Offline, [$question->id], 'Paper', 30, negativeMarks: 0.25);

    expect($exam->negative_marks)->toBeNull();
});
