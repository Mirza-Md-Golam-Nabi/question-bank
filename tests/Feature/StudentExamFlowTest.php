<?php

use App\Enums\ExamType;
use App\Filament\Student\Pages\BuildPracticeExam;
use App\Filament\Student\Pages\ExamResultPage;
use App\Filament\Student\Pages\GeneratePracticeExam;
use App\Filament\Student\Pages\JoinExam;
use App\Filament\Student\Pages\TakeExamPage;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->student = User::factory()->student()->create();
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    $plan = SubscriptionPlan::factory()->forStudents()->monthlyLimit(3)->create();
    Subscription::factory()->create(['user_id' => $this->student->id, 'plan_id' => $plan->id]);

    // Practice is always in the student's own class.
    $this->classSubject = ClassSubject::factory()->create();
    $this->student->update(['academic_class_id' => $this->classSubject->academic_class_id]);
});

it('auto-generates a practice exam from the approved pool and starts an attempt', function () {
    $classSubject = $this->classSubject;
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    Question::factory()->approved()->for($chapter)->count(5)->create();

    livewire(GeneratePracticeExam::class)
        ->fillForm([
            'class_subject_id' => $classSubject->id,
            'question_count' => 3,
        ])
        ->call('start');

    $exam = Exam::where('created_by', $this->student->id)->first();
    expect($exam)->not->toBeNull();
    expect($exam->exam_type)->toBe(ExamType::SelfPractice);
    expect($exam->generation_mode->value)->toBe('auto');
    expect($exam->questions)->toHaveCount(3);
    expect($exam->share_token)->toBeNull();

    expect(ExamAttempt::where('exam_id', $exam->id)->where('student_id', $this->student->id)->exists())->toBeTrue();
});

it('times a practice exam by its questions, rounded up to five minutes by default', function (int $questions, int $minutes) {
    expect(BillingSetting::current()->practiceMinutesFor($questions))->toBe($minutes);
})->with([
    [1, 5], [5, 5], [6, 10], [10, 10], [11, 15], [15, 15], [16, 20], [20, 20], [43, 45],
]);

it('times a practice exam by the Admin\'s minutes per question and rounding', function () {
    BillingSetting::current()->fill(['practice_minutes_per_question' => 1.5, 'practice_minutes_step' => 10])->save();

    // 12 questions × 1.5 = 18 minutes, rounded up to 20.
    expect(BillingSetting::current()->practiceMinutesFor(12))->toBe(20)
        ->and(BillingSetting::current()->practiceMinutesFor(1))->toBe(10);
});

it('gives a practice exam the time of the questions actually on it', function () {
    $chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);
    Question::factory()->approved()->for($chapter)->count(12)->create();

    // Twenty asked for, twelve in the subject: timed for twelve.
    livewire(GeneratePracticeExam::class)
        ->fillForm(['class_subject_id' => $this->classSubject->id, 'question_count' => 20])
        ->call('start');

    $exam = Exam::where('created_by', $this->student->id)->sole();

    expect($exam->questions)->toHaveCount(12)
        ->and($exam->duration_minutes)->toBe(15);
});

it('finds a subject by its English, Bangla or short name', function () {
    $this->classSubject->subject->update(['name' => 'Information Technology', 'name_bn' => 'তথ্য প্রযুক্তি', 'short_name' => 'ICT']);
    ClassSubject::factory()->create(['academic_class_id' => $this->classSubject->academic_class_id]);

    foreach (['information', 'প্রযুক্তি', 'ict'] as $search) {
        livewire(GeneratePracticeExam::class)
            ->assertFormFieldExists('class_subject_id', fn ($field): bool => array_keys($field->getSearchResults($search)) === [$this->classSubject->id]);
    }
});

it('builds a manual practice exam from student-picked questions', function () {
    $classSubject = $this->classSubject;
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $questions = Question::factory()->approved()->for($chapter)->count(2)->create();

    livewire(BuildPracticeExam::class)
        ->fillForm(['class_subject_id' => $classSubject->id])
        ->call('finish', $questions->pluck('id')->all())
        ->assertRedirect();

    $exam = Exam::where('created_by', $this->student->id)->first();
    expect($exam->generation_mode->value)->toBe('manual');
    expect($exam->questions)->toHaveCount(2);
});

it('counts auto and manual self-practice exams together against the same limit', function () {
    $classSubject = $this->classSubject;
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $questions = Question::factory()->approved()->for($chapter)->count(5)->create();

    // limit is 3 — generate 3 exams across both modes, the 4th must be blocked
    foreach (range(1, 3) as $i) {
        livewire(GeneratePracticeExam::class)
            ->fillForm(['class_subject_id' => $classSubject->id, 'question_count' => 1])
            ->call('start');
    }

    expect(Exam::where('created_by', $this->student->id)->count())->toBe(3);

    livewire(BuildPracticeExam::class)
        ->fillForm(['class_subject_id' => $classSubject->id])
        ->call('finish', [$questions->first()->id])
        ->assertNotified(__('The exam could not be started'));

    // still 3 — the 4th attempt was blocked by the monthly limit
    expect(Exam::where('created_by', $this->student->id)->count())->toBe(3);
});

it('lets a logged-in student join a shared exam by its token', function () {
    $exam = Exam::factory()->published()->create();

    livewire(JoinExam::class)
        ->fillForm(['share_token' => $exam->share_token])
        ->call('join');

    expect(ExamAttempt::where('exam_id', $exam->id)->where('student_id', $this->student->id)->exists())->toBeTrue();
});

it('sends a student who already sat a shared exam to their result instead of a second attempt', function () {
    $exam = Exam::factory()->published()->create();
    $attempt = ExamAttempt::startFor($exam, $this->student);
    $attempt->submitAndAutoGrade();

    livewire(JoinExam::class)
        ->fillForm(['share_token' => $exam->share_token])
        ->call('join')
        ->assertRedirect(ExamResultPage::getUrl(['attempt' => $attempt->id, ExamResultPage::ALREADY_TAKEN => 1]));

    expect(ExamAttempt::where('exam_id', $exam->id)->count())->toBe(1);
});

it('sends a student who already sat the exam to their result when they open its share link again', function () {
    $exam = Exam::factory()->published()->create();
    $attempt = ExamAttempt::startFor($exam, $this->student);
    $attempt->submitAndAutoGrade();

    $this->get(route('guest-exam.join', $exam->share_token))
        ->assertRedirect(ExamResultPage::getUrl(['attempt' => $attempt->id, ExamResultPage::ALREADY_TAKEN => 1]));

    expect(ExamAttempt::where('exam_id', $exam->id)->count())->toBe(1);
});

it('tells the student the exam was already taken when they land on the result that way', function () {
    $exam = Exam::factory()->published()->create();
    $attempt = ExamAttempt::startFor($exam, $this->student);
    $attempt->submitAndAutoGrade();

    $this->get(ExamResultPage::getUrl(['attempt' => $attempt->id, ExamResultPage::ALREADY_TAKEN => 1]))
        ->assertOk()
        ->assertSee('You have already taken this exam. Here is your result.');
});

it('puts a student back into the attempt they have under way rather than a new one', function () {
    $exam = Exam::factory()->published()->create();
    $attempt = ExamAttempt::startFor($exam, $this->student);

    $this->get(route('guest-exam.join', $exam->share_token))
        ->assertRedirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));

    expect(ExamAttempt::where('exam_id', $exam->id)->count())->toBe(1);
});

it('hands in a student\'s attempt whose time ran out and sends them to its result when they come back', function () {
    $exam = Exam::factory()->published()->create(['duration_minutes' => 10]);
    $attempt = ExamAttempt::startFor($exam, $this->student);

    $this->travel(11)->minutes();

    $this->get(route('guest-exam.join', $exam->share_token))
        ->assertRedirect(ExamResultPage::getUrl(['attempt' => $attempt->id, ExamResultPage::ALREADY_TAKEN => 1]));

    expect($attempt->refresh()->isInProgress())->toBeFalse();
    expect(ExamAttempt::where('exam_id', $exam->id)->count())->toBe(1);
});

it('lets a student sit a shared exam again after the teacher cancelled it', function () {
    $exam = Exam::factory()->published()->create();
    ExamAttempt::startFor($exam, $this->student)->submitAndAutoGrade();
    $exam->cancel();
    $exam->publish();

    expect(ExamAttempt::startFor($exam, $this->student)->isInProgress())->toBeTrue();
});

it('lets another student sit an exam someone else has already sat', function () {
    $exam = Exam::factory()->published()->create();
    ExamAttempt::startFor($exam, User::factory()->student()->create())->submitAndAutoGrade();

    expect(ExamAttempt::startFor($exam, $this->student)->isInProgress())->toBeTrue();
});

it('closes a logged-in student\'s result back to their dashboard', function () {
    $attempt = ExamAttempt::create([
        'exam_id' => Exam::factory()->published()->create()->id,
        'student_id' => $this->student->id,
        'is_guest' => false,
        'started_at' => now(),
    ]);
    $attempt->submitAndAutoGrade();

    livewire(ExamResultPage::class, ['attempt' => $attempt])
        ->assertSeeHtml('href="'.Dashboard::getUrl(panel: 'student').'"');
});

it('enforces the exam time limit for a logged-in student', function () {
    $this->freezeTime();

    $exam = Exam::factory()->published()->create(['duration_minutes' => 10]);
    $question = Question::factory()->for(Chapter::factory())->approved()->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
    ]);
    $exam->questions()->attach($question->id, ['order_index' => 1, 'marks_override' => null]);

    $attempt = ExamAttempt::create(['exam_id' => $exam->id, 'student_id' => $this->student->id, 'is_guest' => false, 'started_at' => now()])->fresh();

    $page = livewire(TakeExamPage::class, ['attempt' => $attempt])
        ->assertSeeHtml('data-seconds="600"')
        ->set("answers.{$question->id}", 'wrong');

    // The page saves the answers itself as the clock runs out...
    $this->travel(10)->minutes();
    $page->call('saveAnswers');

    // ...so changing one afterwards and submitting late has no effect.
    $this->travel(5)->minutes();
    $page->set("answers.{$question->id}", 'right')->call('submit');

    expect($attempt->answers()->sole()->student_answer)->toBe('wrong');

    // A reload after the save shows the recorded answer again.
    $reloadable = ExamAttempt::create(['exam_id' => $exam->id, 'student_id' => $this->student->id, 'is_guest' => false, 'started_at' => now()])->fresh();
    $reloadable->recordAnswers([$question->id => 'right']);

    livewire(TakeExamPage::class, ['attempt' => $reloadable])->assertSet("answers.{$question->id}", 'right');
});

it('shows a logged-in student the same exam heading and name line a guest sees', function () {
    $classSubject = ClassSubject::factory()->create();
    $classSubject->subject->update(['name' => 'Physics']);

    $exam = Exam::factory()->published()->create([
        'title' => 'Chapter test',
        'subject_id' => $classSubject->subject_id,
        'class_subject_id' => $classSubject->id,
    ]);
    $attempt = ExamAttempt::startFor($exam, $this->student);

    livewire(TakeExamPage::class, ['attempt' => $attempt])
        ->assertSeeInOrder([
            'Physics',
            'Chapter test',
            $classSubject->academicClass->name,
            'Name', $this->student->name,
            'Phone/Email', $this->student->email,
        ])
        // The paper heads itself; the page has no heading of its own.
        ->assertDontSee('Take Exam Page')
        ->assertDontSeeHtml('fi-header-heading');
});

it('auto-generates a practice exam of a chosen difficulty, using only questions of that difficulty', function () {
    $classSubject = $this->classSubject;
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    Question::factory()->approved()->for($chapter)->count(3)->create(['difficulty' => 'easy']);
    $hard = Question::factory()->approved()->for($chapter)->count(2)->create(['difficulty' => 'hard']);

    livewire(GeneratePracticeExam::class)
        ->fillForm([
            'class_subject_id' => $classSubject->id,
            'difficulty' => 'hard',
            'question_count' => 5,
        ])
        ->call('start')
        ->assertHasNoFormErrors();

    $exam = Exam::where('created_by', $this->student->id)->sole();

    expect($exam->questions->pluck('id')->sort()->values()->all())->toBe($hard->pluck('id')->sort()->values()->all());
});
