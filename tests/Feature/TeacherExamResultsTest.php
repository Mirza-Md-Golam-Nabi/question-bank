<?php

use App\Enums\ExamAttemptStatus;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\Exams\Pages\ExamQuestionAnalysis;
use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamResultSheet;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->freezeTime();

    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $this->exam = Exam::factory()->published()->create(['created_by' => $this->teacher->id, 'title' => 'Half yearly', 'total_marks' => 10]);

    /**
     * A submitted guest attempt with the given score, taking `$minutes`.
     */
    $this->guest = fn (string $name, float $score, int $minutes = 5, ?string $contact = null, ?Exam $exam = null): ExamAttempt => ExamAttempt::create([
        'exam_id' => ($exam ?? $this->exam)->id,
        'is_guest' => true,
        'guest_name' => $name,
        'guest_contact' => $contact ?? fake()->unique()->numerify('017########'),
        'started_at' => now()->subMinutes($minutes),
        'submitted_at' => now(),
        'status' => ExamAttemptStatus::Submitted,
        'total_score' => $score,
    ]);

    $this->sheet = fn (?Exam $exam = null) => app(ExamResultSheet::class)->rowsFor($exam ?? $this->exam);
});

it('ranks participants by marks, highest first', function () {
    ($this->guest)('Low', 3);
    ($this->guest)('High', 9);
    ($this->guest)('Middle', 6);

    $rows = ($this->sheet)();

    expect($rows->pluck('name')->all())->toBe(['High', 'Middle', 'Low']);
    expect($rows->pluck('position')->all())->toBe([1, 2, 3]);
});

it('gives equal marks the same position and skips the next one', function () {
    ($this->guest)('A', 9);
    ($this->guest)('B', 7, minutes: 8);
    ($this->guest)('C', 7, minutes: 4);
    ($this->guest)('D', 5);

    $rows = ($this->sheet)();

    expect($rows->pluck('position')->all())->toBe([1, 2, 2, 4]);
    // Within a tie, the faster finisher is listed first.
    expect($rows->pluck('name')->all())->toBe(['A', 'C', 'B', 'D']);
});

it('counts a participant once, on their first submitted attempt', function () {
    ($this->guest)('Rahim Uddin', 2, contact: '01712345678');
    ($this->guest)('rahim  uddin', 10, contact: '017 1234-5678');

    $student = User::factory()->student()->create(['name' => 'Karim']);
    foreach ([4, 10] as $score) {
        ExamAttempt::create([
            'exam_id' => $this->exam->id, 'student_id' => $student->id, 'is_guest' => false,
            'started_at' => now()->subMinutes(5), 'submitted_at' => now(),
            'status' => ExamAttemptStatus::Submitted, 'total_score' => $score,
        ]);
    }

    $rows = ($this->sheet)();

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('score', 'name')->all())->toBe(['Karim' => 4.0, 'Rahim Uddin' => 2.0]);
});

it('leaves out attempts that are still in progress and those of other exams', function () {
    ($this->guest)('Submitted', 5);
    ExamAttempt::create(['exam_id' => $this->exam->id, 'is_guest' => true, 'guest_name' => 'Still writing', 'guest_contact' => '01700000009', 'started_at' => now()]);
    ($this->guest)('Other exam', 9, exam: Exam::factory()->published()->create(['created_by' => $this->teacher->id]));

    expect(($this->sheet)()->pluck('name')->all())->toBe(['Submitted']);
    expect(app(ExamResultSheet::class)->pendingCountFor($this->exam))->toBe(1);
});

it('shows a logged-in student by account name and email', function () {
    $student = User::factory()->student()->create(['name' => 'Karim', 'email' => 'karim@example.com']);
    ExamAttempt::create([
        'exam_id' => $this->exam->id, 'student_id' => $student->id, 'is_guest' => false,
        'started_at' => now()->subMinutes(3), 'submitted_at' => now(),
        'status' => ExamAttemptStatus::Submitted, 'total_score' => 7,
    ]);

    $row = ($this->sheet)()->sole();

    expect($row['name'])->toBe('Karim');
    expect($row['contact'])->toBe('karim@example.com');
    expect($row['duration_seconds'])->toBe(180);
});

it('shows the teacher the ranked results and the number of participants', function () {
    ($this->guest)('Second Place', 6);
    ($this->guest)('First Place', 9);

    livewire(ExamResults::class, ['record' => $this->exam->id])
        ->assertOk()
        ->assertSeeInOrder(['Total participants', '2', 'First Place', 'Second Place']);
});

it('has a back button from the results to the exams list', function () {
    livewire(ExamResults::class, ['record' => $this->exam->id])
        ->assertActionHasUrl('back', ExamResource::getUrl('index'));
});

it('paginates a long result list without losing the overall positions', function () {
    foreach (range(1, 55) as $number) {
        ($this->guest)("Student {$number}", 100 - $number);
    }

    $page = livewire(ExamResults::class, ['record' => $this->exam->id]);

    expect($page->instance()->rows)->toHaveCount(55);
    expect($page->instance()->pageOfRows->count())->toBe(50);

    $page->call('gotoPage', 2);

    expect($page->instance()->pageOfRows->pluck('position')->all())->toBe([51, 52, 53, 54, 55]);
});

it('does not let a teacher open the results of another teacher\'s exam', function () {
    $othersExam = Exam::factory()->published()->create();

    livewire(ExamResults::class, ['record' => $othersExam->id]);
})->throws(ModelNotFoundException::class);

it('offers the results button only for an exam students can take online', function () {
    $draft = Exam::factory()->create(['created_by' => $this->teacher->id]);

    livewire(ListExams::class)
        ->assertTableActionVisible('results', $this->exam)
        ->assertTableActionHidden('results', $draft);
});

it('prints the result sheet with the heading, the total and every participant', function () {
    ($this->guest)('First Place', 9, contact: '01711111111');
    ($this->guest)('Second Place', 6);

    $this->get(route('filament.teacher.exams.results.print', $this->exam))
        ->assertOk()
        ->assertSeeInOrder(['Half yearly', 'Total participants', '2', 'First Place', '01711111111', 'Second Place'])
        ->assertSee('data-qb-download-image', escape: false);
});

it('does not let another teacher print the result sheet', function () {
    $this->actingAs(User::factory()->teacher()->create());

    $this->get(route('filament.teacher.exams.results.print', $this->exam))->assertForbidden();
});

describe('question analysis', function () {
    beforeEach(function () {
        $option = fn (string $text, bool $correct): array => ['option' => $text, 'image' => null, 'is_correct' => $correct];

        // Paper order: easy, hard, skipped.
        [$this->easy, $this->hard, $this->skipped] = collect(['Easy', 'Hard', 'Skipped'])->map(function (string $name, int $index) use ($option) {
            $question = Question::factory()->approved()->for(Chapter::factory())->create([
                'question_text' => "<p>{$name} question</p>",
                'options' => [$option('right', true), $option('wrong', false)],
            ]);
            $this->exam->questions()->attach($question->id, ['order_index' => $index + 1, 'marks_override' => null]);

            return $question;
        })->all();

        /**
         * A participant whose answers are given as question id => chosen option.
         */
        $this->participant = function (string $name, array $answers): ExamAttempt {
            $attempt = ExamAttempt::create([
                'exam_id' => $this->exam->id, 'is_guest' => true, 'guest_name' => $name,
                'guest_contact' => fake()->unique()->numerify('017########'), 'started_at' => now(),
            ])->fresh();
            $attempt->recordAnswers($answers);
            $attempt->submitAndAutoGrade();

            return $attempt;
        };

        $this->stats = fn () => app(ExamResultSheet::class)->questionStatsFor($this->exam->fresh());
    });

    it('counts right, wrong and unanswered per question and lists the most-missed question first', function () {
        ($this->participant)('A', [$this->easy->id => 'right', $this->hard->id => 'wrong']);
        ($this->participant)('B', [$this->easy->id => 'right', $this->hard->id => 'wrong']);
        ($this->participant)('C', [$this->easy->id => 'wrong', $this->hard->id => 'right']);

        $stats = ($this->stats)();

        expect($stats->map(fn (array $stat) => [$stat['question']->id, $stat['correct'], $stat['wrong'], $stat['unanswered']])->all())->toBe([
            [$this->hard->id, 1, 2, 0],
            [$this->easy->id, 2, 1, 0],
            [$this->skipped->id, 0, 0, 3],
        ]);
        expect($stats->first()['participants'])->toBe(3);
    });

    it('keeps the paper order between questions with the same number of wrong answers', function () {
        ($this->participant)('A', [$this->easy->id => 'right', $this->hard->id => 'right', $this->skipped->id => 'right']);

        expect(($this->stats)()->pluck('question.id')->all())->toBe([$this->easy->id, $this->hard->id, $this->skipped->id]);
    });

    it('counts a participant who sat the exam twice only on their first attempt', function () {
        $first = ($this->participant)('Rahim', [$this->hard->id => 'wrong']);
        $first->update(['guest_contact' => '01712345678']);

        $second = ($this->participant)('Rahim', [$this->hard->id => 'right']);
        $second->update(['guest_contact' => '01712345678']);

        $hard = ($this->stats)()->firstWhere('question.id', $this->hard->id);

        expect([$hard['correct'], $hard['wrong'], $hard['participants']])->toBe([0, 1, 1]);
    });

    it('shows the teacher each question with its counts, most-missed first', function () {
        ($this->participant)('A', [$this->easy->id => 'right', $this->hard->id => 'wrong']);

        livewire(ExamQuestionAnalysis::class, ['record' => $this->exam->id])
            ->assertOk()
            // Most wrong answers first; between the other two, the one left blank comes before the one answered right.
            ->assertSeeInOrder(['Hard question', 'Skipped question', 'Easy question']);
    });

    it('links the results page to the question analysis', function () {
        ($this->participant)('A', [$this->easy->id => 'right']);

        livewire(ExamResults::class, ['record' => $this->exam->id])
            ->assertActionHasUrl('questionAnalysis', ExamQuestionAnalysis::getUrl(['record' => $this->exam]));
    });

    it('does not open the analysis of another teacher\'s exam', function () {
        livewire(ExamQuestionAnalysis::class, ['record' => Exam::factory()->published()->create()->id]);
    })->throws(ModelNotFoundException::class);
});

describe('drilling into the results', function () {
    beforeEach(function () {
        $this->question = Question::factory()->approved()->for(Chapter::factory())->create([
            'question_text' => '<p>Capital of Bangladesh?</p>',
            'options' => [
                ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
                ['option' => 'Khulna', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $this->exam->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);

        $this->participant = function (string $name, ?string $answer, ?Exam $exam = null): ExamAttempt {
            $attempt = ExamAttempt::create([
                'exam_id' => ($exam ?? $this->exam)->id, 'is_guest' => true, 'guest_name' => $name,
                'guest_contact' => fake()->unique()->numerify('017########'), 'started_at' => now(),
            ])->fresh();
            $attempt->recordAnswers($answer === null ? [] : [$this->question->id => $answer]);
            $attempt->submitAndAutoGrade();

            return $attempt;
        };

        /**
         * The HTML of the modal an action opens. Filament renders action
         * modals as a separate partial, so the page HTML doesn't hold them.
         */
        $this->modalOf = fn ($page, string $action, array $arguments): string => $page
            ->mountAction($action, $arguments)
            ->instance()
            ->getMountedAction()
            ->getModalContent()
            ->render();
    });

    it('lists exactly the students who got a question wrong, with what they chose', function () {
        ($this->participant)('Right One', 'Dhaka');
        ($this->participant)('Wrong One', 'Khulna');
        ($this->participant)('Blank One', null);

        $wrong = app(ExamResultSheet::class)->wrongAnswersFor($this->exam->fresh(), $this->question->id);

        expect($wrong->pluck('name')->all())->toBe(['Wrong One']);
        expect($wrong->first()['answer'])->toBe('Khulna');
    });

    it('lists nobody for a question that is not part of the exam', function () {
        ($this->participant)('Wrong One', 'Khulna');

        $foreign = Question::factory()->approved()->for(Chapter::factory())->create();

        expect(app(ExamResultSheet::class)->wrongAnswersFor($this->exam->fresh(), $foreign->id))->toBeEmpty();
    });

    it('shows the teacher who answered a question wrongly from the analysis page', function () {
        ($this->participant)('Right One', 'Dhaka');
        ($this->participant)('Wrong One', 'Khulna');

        $page = livewire(ExamQuestionAnalysis::class, ['record' => $this->exam->id])
            ->assertSee('See who answered wrongly')
            // The names are not on the page until asked for.
            ->assertDontSee('Wrong One');

        expect(($this->modalOf)($page, 'wrongStudents', ['question' => $this->question->id]))
            ->toContain('Wrong One')
            ->not->toContain('Right One');
    });

    it('opens one student\'s answers from the eye button, correct answer and their own pick marked', function () {
        $attempt = ($this->participant)('Wrong One', 'Khulna');

        $page = livewire(ExamResults::class, ['record' => $this->exam->id])
            ->assertSeeHtml("mountAction('viewAnswers', { attempt: {$attempt->id} })");

        expect(($this->modalOf)($page, 'viewAnswers', ['attempt' => $attempt->id]))
            ->toContain('Capital of Bangladesh?', 'qb-result-option--correct', 'qb-result-option--wrong')
            // Whose paper it is: both the name and the phone/email.
            ->toContain('Wrong One', $attempt->guest_contact);
    });

    it('shows the teacher a student\'s answers even while they are still hidden from students', function () {
        $attempt = ($this->participant)('Wrong One', 'Khulna');

        expect($this->exam->fresh()->showsAnswersToStudents())->toBeFalse();

        $page = livewire(ExamResults::class, ['record' => $this->exam->id]);

        expect(($this->modalOf)($page, 'viewAnswers', ['attempt' => $attempt->id]))->toContain('Dhaka');
    });

    it('never opens an attempt that belongs to another exam', function () {
        $otherExam = Exam::factory()->published()->create();
        $otherExam->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);
        $foreignAttempt = ($this->participant)('Someone Else', 'Khulna', $otherExam);

        expect(app(ExamResultSheet::class)->attemptFor($this->exam, $foreignAttempt->id))->toBeNull();

        $page = livewire(ExamResults::class, ['record' => $this->exam->id]);

        expect(($this->modalOf)($page, 'viewAnswers', ['attempt' => $foreignAttempt->id]))
            ->toContain('This result could not be found.')
            ->not->toContain('Capital of Bangladesh?');
    });

    it('keeps the eye button off the printable sheet', function () {
        ($this->participant)('Wrong One', 'Khulna');

        $this->get(route('filament.teacher.exams.results.print', $this->exam))
            ->assertOk()
            ->assertDontSee('viewAnswers', escape: false);
    });
});
