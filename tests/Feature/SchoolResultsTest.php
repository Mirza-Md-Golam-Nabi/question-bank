<?php

use App\Enums\ExamDeliveryMode;
use App\Filament\School\Pages\SendToSchoolResults;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SchoolProfile;
use App\Models\SchoolResultPush;
use App\Models\User;
use App\Services\SchoolResultMatcher;
use App\Services\TeacherExamBuilder;
use App\Support\SchoolConnectionSignature;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->school = User::factory()->school()->googleAuthenticated()->create();
    $this->profile = SchoolProfile::factory()->connected('https://school.test')->create(['user_id' => $this->school->id]);
    subscribeToEverything($this->school);

    $classSubject = ClassSubject::factory()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $this->questions = Question::factory()->approved()->for($chapter)->count(2)->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 1,
    ]);

    // A School exam out of 2 marks.
    $this->exam = app(TeacherExamBuilder::class)->build($this->school, $classSubject, ExamDeliveryMode::Online, $this->questions->pluck('id')->all(), 'Class test', 20);
    $this->exam->publish();
    $this->exam->refresh();

    /** A handed-in paper scoring `$score` of 2, as the entry form would make it. */
    $this->paper = function (string $roll, string $name, int $score, ?int $sectionId = 91) {
        $attempt = ExamAttempt::startForSchoolStudent($this->exam, ['id' => 9, 'name' => 'Class Nine'], $sectionId ? ['id' => $sectionId, 'name' => 'A'] : null, $roll, $name);
        $attempt->recordAnswers($this->questions->take($score)->mapWithKeys(fn (Question $question) => [$question->id => 'right'])->all());
        $attempt->submitAndAutoGrade();

        return $attempt->refresh();
    };

    $this->student = fn (int $id, string $roll, string $name, array $extra = []): array => [
        'id' => $id, 'roll' => $roll, 'name' => $name, 'section_id' => 91, 'section_name' => 'A',
        'mcq_marks' => null, 'is_absent' => false, 'has_other_marks' => false, ...$extra,
    ];
});

describe('matching papers to the school list', function () {
    it('sorts every student into matched, name to approve, roll used twice, or absent', function () {
        ($this->paper)('1', 'Rahim Uddin', 2);
        ($this->paper)('2', 'Karim', 1);
        ($this->paper)('3', 'Salma', 2);
        ($this->paper)('3', 'Someone else', 0);
        ($this->paper)('99', 'Not on the list', 2);

        $match = app(SchoolResultMatcher::class)->match($this->exam, 9, [
            ($this->student)(101, '1', 'Md. Rahim Uddin'),
            ($this->student)(102, '2', 'Abdul Karim Khan'),
            ($this->student)(103, '3', 'Salma Akter'),
            ($this->student)(104, '4', 'Nadia'),
        ]);

        expect(collect($match['rows'])->map(fn (array $row): string => $row['status'])->all())->toBe([
            SchoolResultMatcher::MATCHED,
            SchoolResultMatcher::NAME_MISMATCH,
            SchoolResultMatcher::DUPLICATE,
            SchoolResultMatcher::ABSENT,
        ])
            ->and($match['rows'][2]['attempts'])->toHaveCount(2)
            ->and($match['unmatched']->pluck('guest_name')->all())->toBe(['Not on the list']);
    });

    it('tells sections apart', function () {
        ($this->paper)('5', 'Rahim', 2, sectionId: 92);

        $match = app(SchoolResultMatcher::class)->match($this->exam, 9, [($this->student)(101, '5', 'Rahim')]);

        expect($match['rows'][0]['status'])->toBe(SchoolResultMatcher::ABSENT)
            ->and($match['unmatched'])->toHaveCount(1);
    });

    it('reads the usual name prefixes and spellings as the same name', function (string $one, string $other, bool $same) {
        expect(SchoolResultMatcher::sameName($one, $other))->toBe($same);
    })->with([
        ['Md. Rahim Uddin', 'rahim  uddin', true],
        ['Mohammad Karim', 'MD KARIM', true],
        ['মোঃ রহিম উদ্দিন', 'রহিম উদ্দিন', true],
        ['মোছাঃ সালমা', 'সালমা', true],
        ['Rahim', 'Karim', false],
        ['', '', false],
    ]);

    it('gives the marks as they are, or in proportion, never above the full marks', function () {
        $paper = ($this->paper)('1', 'Rahim', 1);

        expect(SchoolResultMatcher::marksFor($paper, $this->exam, 2, false))->toBe(1.0)
            ->and(SchoolResultMatcher::marksFor($paper, $this->exam, 30, true))->toBe(15.0)
            ->and(SchoolResultMatcher::marksFor(($this->paper)('2', 'Karim', 2), $this->exam, 1, false))->toBe(1.0);
    });
});

describe('the send page', function () {
    beforeEach(function () {
        $this->actingAs($this->school);
        Filament::setCurrentPanel(Filament::getPanel('school'));

        $this->sheet = fn (float $fullMarks = 2, array $students = []): array => [
            'mcq_total' => $fullMarks,
            'is_published' => false,
            'students' => $students ?: [($this->student)(101, '1', 'Rahim Uddin'), ($this->student)(102, '2', 'Karim')],
        ];

        $this->fakeSchool = function (array $sheet, array $answer = ['saved' => 1, 'skipped' => []], int $status = 200): void {
            Http::fake([
                'school.test/api/question-bank/classes/9/exams' => Http::response(['exams' => [[
                    'id' => 7, 'name' => 'Half Yearly - Class 9 (2026)', 'session_year' => 2026, 'is_published' => false,
                    'subjects' => [['id' => 3, 'name' => 'Physics', 'name_bn' => null, 'mcq_total' => $sheet['mcq_total']]],
                ]]]),
                'school.test/api/question-bank/exams/7/subjects/3/students' => Http::response($sheet),
                'school.test/api/question-bank/exams/7/subjects/3/results' => Http::response($answer, $status),
            ]);
        };

        $this->page = fn () => livewire(SendToSchoolResults::class, ['exam' => $this->exam->id])
            ->set('classId', 9)
            ->set('schoolExamId', 7)
            ->set('subjectId', 3);
    });

    it('sends the matched marks and the absent, signed, and keeps a record', function () {
        ($this->paper)('1', 'Rahim Uddin', 2);
        ($this->fakeSchool)(($this->sheet)(), ['saved' => 2, 'skipped' => []]);

        ($this->page)()
            ->call('loadSheet')
            ->assertActionNotMounted('fullMarks')
            ->callAction('send')
            ->assertNotified(__(':count marks saved in the school results', ['count' => 2]));

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/results')
            && $request['overwrite'] === false
            && $request['marks'] === [
                ['student_id' => 101, 'mcq_marks' => 2, 'absent' => false],
                ['student_id' => 102, 'mcq_marks' => null, 'absent' => true],
            ]
            && SchoolConnectionSignature::verify($this->profile->connection_secret, $request->header('X-Timestamp')[0], $request->body(), $request->header('X-Signature')[0]));

        expect(SchoolResultPush::query()->sole())->toMatchArray([
            'exam_id' => $this->exam->id,
            'school_exam_id' => 7,
            'school_subject_id' => 3,
            'sent_count' => 2,
            'saved_count' => 2,
            'in_proportion' => false,
        ]);
    });

    it('sends a name that does not match only once approved, and a roll used twice only once one is picked', function () {
        ($this->paper)('1', 'Someone', 2);
        $first = ($this->paper)('2', 'Karim', 1);
        ($this->paper)('2', 'Not Karim', 2);
        ($this->fakeSchool)(($this->sheet)());

        $page = ($this->page)()->call('loadSheet');

        // Rahim (name not matching) and Karim (two papers) wait for the School.
        expect($page->instance()->marksToSend())->toBe([]);

        $page->set('choices.101.send', true)->set('choices.102.attempt', $first->id);

        expect($page->instance()->marksToSend())->toBe([
            ['student_id' => 101, 'mcq_marks' => 2.0, 'absent' => false],
            ['student_id' => 102, 'mcq_marks' => 1.0, 'absent' => false],
        ]);
    });

    it('asks what to do when the full marks differ — in proportion, or fix them in the school software', function () {
        ($this->paper)('1', 'Rahim Uddin', 1);
        ($this->fakeSchool)(($this->sheet)(fullMarks: 30));

        ($this->page)()
            ->call('loadSheet')
            ->assertActionMounted('fullMarks')
            ->callMountedAction()
            ->assertSet('inProportion', true)
            ->tap(fn ($page) => expect($page->instance()->marksToSend()[0]['mcq_marks'])->toBe(15.0));

        ($this->page)()
            ->call('loadSheet')
            ->call('cancelFullMarks')
            ->assertSet('sheet', null);
    });

    it('passes "replace marks already there" on, and shows what the school left as it was', function () {
        ($this->paper)('1', 'Rahim Uddin', 2);
        ($this->fakeSchool)(($this->sheet)(students: [($this->student)(101, '1', 'Rahim Uddin', ['mcq_marks' => 1])]), ['saved' => 0, 'skipped' => [['student_id' => 101, 'reason' => 'has_marks']]]);

        ($this->page)()
            ->call('loadSheet')
            ->set('overwrite', true)
            ->callAction('send')
            ->assertSee(SendToSchoolResults::skipReason('has_marks'));

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/results') && $request['overwrite'] === true);
    });

    it('says so when the school software refuses, and records nothing', function () {
        ($this->paper)('1', 'Rahim Uddin', 2);
        ($this->fakeSchool)(($this->sheet)(), ['message' => 'published'], 409);

        ($this->page)()
            ->call('loadSheet')
            ->callAction('send')
            ->assertNotified(__('The marks were not sent'));

        expect(SchoolResultPush::query()->count())->toBe(0);
    });

    it('opens only for the school\'s own online exams', function () {
        $other = Exam::factory()->published()->create();

        $this->get(SendToSchoolResults::getUrl(['exam' => $other->id]))->assertNotFound();
        $this->get(SendToSchoolResults::getUrl(['exam' => $this->exam->id]))->assertOk();
    });
});
