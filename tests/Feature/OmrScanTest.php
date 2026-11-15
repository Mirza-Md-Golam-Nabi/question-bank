<?php

use App\Enums\ExamDeliveryMode;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\ExamResultSheet;
use App\Services\SchoolResultMatcher;
use App\Support\OmrSheet;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    subscribeToEverything($this->teacher);
    $this->actingAs($this->teacher);

    $chapter = Chapter::factory()->create();
    $mcq = fn (): Question => Question::factory()->approved()->for($chapter)->create([
        'marks' => 1,
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
            ['option' => 'also wrong', 'image' => null, 'is_correct' => false],
        ],
    ]);
    $this->first = $mcq();
    $this->second = $mcq();
    $this->third = $mcq();
    $cq = Question::factory()->cq()->approved()->for($chapter)->create();
    $cq->cqParts()->create(['part_type' => 'knowledge', 'part_order' => 1, 'part_text' => '<p>What?</p>', 'marks' => 10]);

    // A print-only paper: three MCQ of 1 mark each and a CQ of 10.
    $this->exam = Exam::factory()->delivery(ExamDeliveryMode::Offline)->create(['created_by' => $this->teacher->id]);
    $this->exam->questions()->attach([
        $this->first->id => ['order_index' => 1, 'marks_override' => null],
        $this->second->id => ['order_index' => 2, 'marks_override' => null],
        $cq->id => ['order_index' => 3, 'marks_override' => null],
        $this->third->id => ['order_index' => 4, 'marks_override' => null],
    ]);
    $this->exam->recalculateTotalMarks();

    $this->save = fn (array $data, ?Exam $exam = null) => $this->postJson(
        route('filament.teacher.exams.omr-scan.store', $exam ?? $this->exam),
        ['roll' => '12', 'name' => null, 'answers' => [], ...$data],
    );
});

describe('the sheet', function () {
    it('lays out every mcq of the paper, numbered as printed, with a circle per option', function () {
        $page = OmrSheet::for($this->exam)->pages()[0];

        expect(collect($page['questions'])->map(fn (array $question): array => [$question['number'], $question['key'], count($question['options'])])->all())
            ->toBe([[1, (string) $this->first->id, 3], [2, (string) $this->second->id, 3], [3, (string) $this->third->id, 3]])
            ->and($page['code'])->toMatch('/^[01]{32}$/');
    });

    it('gets another code when the paper changes, so an old sheet is never read as the new one', function () {
        $before = OmrSheet::for($this->exam)->pageCode(0);

        $this->exam->questions()->detach($this->third->id);

        expect(OmrSheet::for($this->exam->fresh())->pageCode(0))->not->toBe($before)
            ->and(OmrSheet::for($this->exam->fresh())->pageCode(0))->toBe(OmrSheet::for($this->exam->fresh())->pageCode(0));
    });

    it('goes on to a second sheet when the questions do not fit on one, the numbers running on', function () {
        $questions = collect(range(1, 130))->map(fn (int $id): Question => (new Question)->forceFill([
            'id' => $id,
            'question_type' => 'mcq',
            'options' => [
                ['option' => 'a', 'image' => null, 'is_correct' => true],
                ['option' => 'b', 'image' => null, 'is_correct' => false],
            ],
        ]));

        $pages = (new OmrSheet($this->exam, $questions))->pages();

        $onFirst = count($pages[0]['questions']);

        expect($pages)->toHaveCount(2)
            ->and($onFirst)->toBe(3 * OmrSheet::MAX_ROWS)
            ->and($pages[1]['questions'][0]['number'])->toBe($onFirst + 1)
            ->and($pages[0]['code'])->not->toBe($pages[1]['code']);

        // Every circle left of the number grids, above the bottom corner squares, and never smaller than the least.
        $rightmostGrid = collect(OmrSheet::grids())->min('x');
        foreach ($pages as $page) {
            $circles = collect($page['questions'])->flatMap(fn (array $question) => $question['options']);
            expect($circles->min(0) - $page['bubble'] / 2)->toBeGreaterThan(OmrSheet::QUESTIONS_LEFT)
                ->and($circles->max(0) + $page['bubble'] / 2)->toBeLessThan($rightmostGrid)
                ->and($circles->min(1))->toBeGreaterThan(OmrSheet::BODY_TOP + OmrSheet::COLUMN_HEAD)
                ->and($circles->max(1) + $page['bubble'] / 2)->toBeLessThanOrEqual(OmrSheet::ROWS_BOTTOM)
                ->and($page['bubble'])->toBeGreaterThanOrEqual(OmrSheet::MIN_BUBBLE);
        }
    });

    it('gives a short paper fewer, roomier rows — thirty questions in two columns of fifteen', function () {
        $questions = collect(range(1, 30))->map(fn (int $id): Question => (new Question)->forceFill([
            'id' => $id,
            'question_type' => 'mcq',
            'options' => array_fill(0, 4, ['option' => 'a', 'image' => null, 'is_correct' => false]),
        ]));

        $page = (new OmrSheet($this->exam, $questions))->pages()[0];

        expect(array_column($page['columns'], 'rows'))->toBe([15, 15])
            ->and($page['bubble'])->toBe(OmrSheet::MAX_BUBBLE)
            ->and($page['rowPitch'])->toEqual(OmrSheet::MAX_ROW_PITCH);
    });

    it('keeps the number grids and boxes inside the frame, clear of each other and of the corner squares', function () {
        $areas = collect(OmrSheet::grids())
            ->map(fn (array $grid): array => [$grid['x'], $grid['y'], $grid['x'] + $grid['columns'] * $grid['pitch'], $grid['y'] + OmrSheet::gridHeight($grid)])
            ->merge(collect(OmrSheet::boxes())->map(fn (array $box): array => [$box['x'], $box['y'], $box['x'] + $box['width'], $box['y'] + $box['height']]))
            ->values();

        foreach ($areas as $index => [$left, $top, $right, $bottom]) {
            expect($left)->toBeGreaterThan(OmrSheet::MARKER_SIZE)
                ->and($right)->toBeLessThan(OmrSheet::WIDTH - OmrSheet::MARKER_SIZE / 2)
                ->and($top)->toBeGreaterThanOrEqual(OmrSheet::BODY_TOP)
                ->and($bottom)->toBeLessThan(OmrSheet::HEIGHT - OmrSheet::MARKER_SIZE);

            foreach ($areas->slice($index + 1) as [$otherLeft, $otherTop, $otherRight, $otherBottom]) {
                expect($left < $otherRight && $otherLeft < $right && $top < $otherBottom && $otherTop < $bottom)->toBeFalse();
            }
        }
    });

    it('has as many rows as the teacher chose, the ones after the paper blank and never read', function () {
        $before = OmrSheet::for($this->exam)->pageCode(0);

        $this->post(route('filament.teacher.exams.omr-questions', $this->exam), ['questions' => 10])
            ->assertRedirect(route('filament.teacher.exams.print', ['exam' => $this->exam, 'answers' => 'omr']));

        $sheet = OmrSheet::for($this->exam->fresh());
        $rows = collect($sheet->pages()[0]['questions']);

        expect($this->exam->fresh()->omr_questions)->toBe(10)
            ->and($rows->pluck('number')->all())->toBe(range(1, 10))
            ->and($rows->skip(3)->pluck('key')->unique()->all())->toBe([null])
            ->and(count($rows->last()['options']))->toBe(4)
            ->and(array_column($sheet->scannerLayout()['pages'][0]['questions'], 'key'))->toBe([(string) $this->first->id, (string) $this->second->id, (string) $this->third->id])
            ->and($sheet->pageCode(0))->not->toBe($before);
    });

    it('can have fewer rows than the paper has mcq, the last ones then left off the sheet and never read', function () {
        $this->post(route('filament.teacher.exams.omr-questions', $this->exam), ['questions' => 2])
            ->assertSessionHasNoErrors();

        $sheet = OmrSheet::for($this->exam->fresh());

        expect($this->exam->fresh()->omr_questions)->toBe(2)
            ->and(collect($sheet->pages()[0]['questions'])->pluck('number')->all())->toBe([1, 2])
            ->and(array_column($sheet->scannerLayout()['pages'][0]['questions'], 'key'))->toBe([(string) $this->first->id, (string) $this->second->id])
            ->and(array_keys($sheet->answersFrom([(string) $this->first->id => 0, (string) $this->third->id => 0])))->toEqual([$this->first->id]);
    });

    it('takes 1 to the most rows, and keeps just the paper as following it', function () {
        $this->post(route('filament.teacher.exams.omr-questions', $this->exam), ['questions' => 0])
            ->assertSessionHasErrors('questions');
        $this->post(route('filament.teacher.exams.omr-questions', $this->exam), ['questions' => OmrSheet::MAX_QUESTIONS + 1])
            ->assertSessionHasErrors('questions');

        $this->post(route('filament.teacher.exams.omr-questions', $this->exam), ['questions' => 3]);

        expect($this->exam->fresh()->omr_questions)->toBeNull()
            ->and(OmrSheet::for($this->exam->fresh())->questionCount())->toBe(3);
    });

    it('lets no one else choose the rows', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->post(route('filament.teacher.exams.omr-questions', $this->exam), ['questions' => 10])
            ->assertForbidden();

        expect($this->exam->fresh()->omr_questions)->toBeNull();
    });

    it('prints the sheet with the roll number grid', function () {
        $this->get(route('filament.teacher.exams.print', ['exam' => $this->exam, 'answers' => 'omr']))
            ->assertOk()
            ->assertSee('data-omr-roll-column="'.(OmrSheet::ROLL_DIGITS - 1).'"', escape: false)
            ->assertSee(__('Answer sheet of the objective test'));
    });
});

describe('the scanner page', function () {
    it('opens for the teacher with the sheet\'s layout', function () {
        $this->get(route('filament.teacher.exams.omr-scan', $this->exam))
            ->assertOk()
            ->assertSee('qb-omr-scan-data', escape: false)
            ->assertSee(OmrSheet::for($this->exam)->pageCode(0));
    });

    it('is not for another teacher, nor for an exam with no printed mcq', function () {
        $online = Exam::factory()->delivery(ExamDeliveryMode::Online)->create(['created_by' => $this->teacher->id]);
        $online->questions()->attach($this->first->id, ['order_index' => 1, 'marks_override' => null]);

        $this->get(route('filament.teacher.exams.omr-scan', $online))->assertNotFound();
        ($this->save)([], $online)->assertNotFound();

        $this->actingAs(tap(User::factory()->teacher()->create(), fn (User $other) => subscribeToEverything($other)));

        $this->get(route('filament.teacher.exams.omr-scan', $this->exam))->assertForbidden();
        ($this->save)([])->assertForbidden();
    });

    it('is in the exam list\'s menu of a printed paper with mcq', function () {
        Filament::setCurrentPanel('teacher');

        livewire(ListExams::class)
            ->assertTableActionHasUrl('scanOmrSheets', route('filament.teacher.exams.omr-scan', $this->exam), $this->exam);
    });
});

describe('saving a scanned sheet', function () {
    it('marks it like any paper, out of the mcq marks alone', function () {
        ($this->save)([
            'roll' => '০১২',
            'name' => 'Rahim',
            'answers' => [
                (string) $this->first->id => 0,        // right
                (string) $this->second->id => 'several', // more than one filled: wrong
                (string) $this->third->id => null,      // blank
            ],
        ])->assertOk()->assertJson(['roll' => '12', 'score' => 1, 'out_of' => 3]);

        $attempt = ExamAttempt::sole();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        expect($attempt->is_omr)->toBeTrue()
            ->and($attempt->student_id)->toBeNull()
            ->and($attempt->guest_name)->toBe('Rahim')
            ->and((float) $attempt->total_score)->toBe(1.0)
            ->and($answers[$this->first->id]->is_correct)->toBeTrue()
            ->and($answers[$this->second->id]->student_answer)->toBe(OmrSheet::SEVERAL_FILLED)
            ->and($answers[$this->second->id]->is_correct)->toBeFalse()
            ->and($answers->has($this->third->id))->toBeFalse()
            // The paper's total still counts the CQ; the sheet is out of the MCQ.
            ->and((float) $this->exam->fresh()->total_marks)->toBe(13.0)
            ->and($this->exam->fresh()->scoredMarks())->toBe(3.0)
            // Counted in each question's difficulty, blank and all.
            ->and($this->third->fresh()->answer_count)->toBe(1);
    });

    it('ranks it on the result sheet by roll, without a time', function () {
        ($this->save)(['roll' => '7', 'answers' => [(string) $this->first->id => 0]])->assertOk();

        $row = app(ExamResultSheet::class)->rowsFor($this->exam)->sole();

        expect($row['contact'])->toBe('Roll 7 · OMR')
            ->and($row['duration_seconds'])->toBeNull();

        Filament::setCurrentPanel('teacher');
        livewire(ListExams::class)->assertTableActionVisible('results', $this->exam);
    });

    it('scores it in the analytics as a share of the mcq marks', function () {
        ($this->save)(['answers' => [(string) $this->first->id => 0, (string) $this->second->id => 0, (string) $this->third->id => 1]])->assertOk();

        $percent = DB::table('exam_attempts as attempt')
            ->join('exams', 'exams.id', '=', 'attempt.exam_id')
            ->selectRaw(ExamAttempt::SCORE_PERCENT_SQL.' as percent')
            ->value('percent');

        expect(round((float) $percent, 2))->toBe(66.67);
    });

    it('takes a roll only once', function () {
        ($this->save)(['roll' => '12'])->assertOk();

        ($this->save)(['roll' => '012'])->assertUnprocessable()->assertJsonValidationErrors('roll');
        ($this->save)(['roll' => ' '])->assertUnprocessable()->assertJsonValidationErrors('roll');
        ($this->save)(['roll' => '13'])->assertOk();

        expect(ExamAttempt::count())->toBe(2);
    });

    it('stops the paper from being edited, as any student\'s paper does', function () {
        ($this->save)([])->assertOk();

        Filament::setCurrentPanel('teacher');
        livewire(ListExams::class)->assertTableActionHidden('editQuestions', $this->exam);
    });

    it('leaves out a question that is not on the paper', function () {
        $elsewhere = Question::factory()->approved()->create();

        ($this->save)(['answers' => [(string) $elsewhere->id => 0]])->assertOk();

        expect(ExamAttempt::sole()->answers()->count())->toBe(0);
    });
});

describe('a School', function () {
    beforeEach(function () {
        Cache::flush();
        $this->school = User::factory()->school()->googleAuthenticated()->create();
        SchoolProfile::factory()->connected('https://school.test')->create(['user_id' => $this->school->id]);
        subscribeToEverything($this->school);
        Http::fake(fn () => Http::response(['classes' => [
            ['id' => 9, 'name' => 'Class Nine', 'name_bn' => null, 'sections' => [['id' => 91, 'name' => 'A']]],
        ]]));

        $this->exam->forceFill(['created_by' => $this->school->id])->save();
        $this->actingAs($this->school);

        $this->schoolSave = fn (array $data) => $this->postJson(
            route('filament.school.exams.omr-scan.store', $this->exam),
            ['roll' => '15', 'answers' => [(string) $this->first->id => 0], ...$data],
        );
    });

    it('scans one class and section, which the sheets are kept under for the school\'s results', function () {
        $this->get(route('filament.school.exams.omr-scan', $this->exam))->assertOk()->assertSee('Class Nine');

        ($this->schoolSave)([])->assertUnprocessable()->assertJsonValidationErrors('class_id');
        ($this->schoolSave)(['class_id' => 9])->assertUnprocessable()->assertJsonValidationErrors('class_id');
        ($this->schoolSave)(['class_id' => 9, 'section_id' => 91])->assertOk();

        $attempt = ExamAttempt::sole();
        expect([$attempt->school_class_id, $attempt->school_section_name, $attempt->school_roll])->toBe([9, 'A', '15']);

        $match = app(SchoolResultMatcher::class)->match($this->exam, 9, [['id' => 1, 'roll' => '15', 'name' => 'Karim', 'section_id' => 91]]);
        expect($match['rows'][0]['attempts']->sole()->id)->toBe($attempt->id);

        $this->get("/school/exams/{$this->exam->id}/send-to-school-results")->assertOk();
    });
});
