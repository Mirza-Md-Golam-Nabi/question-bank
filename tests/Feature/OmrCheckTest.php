<?php

use App\Enums\ExamType;
use App\Enums\OmrIdentity;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Filament\School\Pages\SendToSchoolResults;
use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Filament\Teacher\Resources\OmrChecks\Pages\ListOmrChecks;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SchoolProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ExamResultSheet;
use App\Services\OmrCheckService;
use App\Services\SchoolResultMatcher;
use App\Support\OmrSheet;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    subscribeToEverything($this->teacher);
    $this->actingAs($this->teacher);

    /** An OMR check of five questions (right: A, C, B, D, A). */
    $this->makeCheck = fn (array $data = [], ?User $user = null): Exam => app(OmrCheckService::class)->save($user ?? $this->teacher, [
        'title' => 'Weekly test',
        'questions' => 5,
        'options' => 4,
        'marks' => 1,
        'negative_marks' => null,
        'omr_identity' => OmrIdentity::Roll->value,
        'answers' => [0, 2, 1, 3, 0],
        ...$data,
    ]);

    $this->save = fn (Exam $exam, array $data) => $this->postJson(
        route('filament.teacher.exams.omr-scan.store', $exam),
        ['roll' => '12', 'name' => null, 'answers' => [], ...$data],
    );
});

describe('making an OMR check', function () {
    it('keeps the answer key on an exam of its own type, with no questions and nothing counted against the plan', function () {
        $check = ($this->makeCheck)(['marks' => 2]);

        expect($check->exam_type)->toBe(ExamType::OmrCheck)
            ->and($check->answerKey())->toBe(['options' => 4, 'marks' => 2.0, 'answers' => [0, 2, 1, 3, 0]])
            ->and($check->questions()->count())->toBe(0)
            ->and($check->scoredMarks())->toBe(10.0)
            ->and($check->subscription_id)->toBeNull()
            ->and($check->takesOmrSheets())->toBeTrue()
            ->and(Exam::createdThisMonthBy($this->teacher, ExamType::TeacherExam)->count())->toBe(0);
    });

    it('starts with four options, one mark, no negative marking and the roll, and goes to the OMR page once saved', function () {
        Filament::setCurrentPanel('teacher');

        livewire(ListOmrChecks::class)
            ->mountAction('create')
            ->assertSchemaStateSet(['options' => 4, 'marks' => 1, 'negative_marking' => false, 'omr_identity' => OmrIdentity::Roll])
            ->setActionData(['title' => 'Class test', 'questions' => 3, 'answers' => [1, 0, 2]])
            ->callMountedAction()
            ->assertNotified();

        $check = Exam::sole();

        expect($check->answerKey()['answers'])->toBe([1, 0, 2])
            ->and($check->negative_marks)->toBeNull();
    });

    it('wants the right answer of every question, and an option the question has', function (array $data) {
        expect(fn () => ($this->makeCheck)($data))->toThrow(ValidationException::class);

        expect(Exam::count())->toBe(0);
    })->with([
        'one missing' => [['answers' => [0, 2, 1, 3]]],
        'a blank' => [['answers' => [0, 2, null, 3, 0]]],
        'no such option' => [['answers' => [0, 2, 1, 4, 0]]],
        'no questions' => [['questions' => 0, 'answers' => []]],
        'too many questions' => [['questions' => OmrSheet::MAX_QUESTIONS + 1, 'answers' => array_fill(0, OmrSheet::MAX_QUESTIONS + 1, 0)]],
    ]);

    it('needs a plan with the OMR check', function () {
        $teacher = User::factory()->teacher()->create();
        $plan = SubscriptionPlan::factory()->pro()->withConditions([
            SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [PlanFeature::Print->value]]),
        ])->create(['target_role' => $teacher->role->subscriptionTargetRole()]);
        Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

        expect(fn () => ($this->makeCheck)([], $teacher))->toThrow(ValidationException::class, 'OMR check');

        $plan->update(['conditions' => [SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [PlanFeature::OmrCheck->value]])]]);
        Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

        expect(($this->makeCheck)([], $teacher->fresh())->isOmrCheck())->toBeTrue();
    });

    it('is not for a student', function () {
        expect(fn () => ($this->makeCheck)([], User::factory()->student()->create()))->toThrow(ValidationException::class);
    });

    it('lists only the user\'s own checks, apart from their exams', function () {
        Filament::setCurrentPanel('teacher');
        $mine = ($this->makeCheck)();
        $theirs = ($this->makeCheck)([], tap(User::factory()->teacher()->create(), fn (User $other) => subscribeToEverything($other)));
        $exam = Exam::factory()->create(['created_by' => $this->teacher->id]);

        livewire(ListOmrChecks::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs, $exam]);
        livewire(ListExams::class)->assertCanSeeTableRecords([$exam])->assertCanNotSeeTableRecords([$mine]);
    });
});

describe('its sheet', function () {
    it('has a row per question of the key, read by number', function () {
        $sheet = OmrSheet::for(($this->makeCheck)());
        $page = $sheet->pages()[0];

        expect(collect($page['questions'])->pluck('key')->all())->toBe(['k1', 'k2', 'k3', 'k4', 'k5'])
            ->and(array_column($page['columns'], 'rows'))->toBe([3, 2])
            ->and(array_column($sheet->scannerLayout()['pages'][0]['questions'], 'key'))->toBe(['k1', 'k2', 'k3', 'k4', 'k5']);
    });

    it('prints as the OMR sheet and the answer key, but has no question paper', function () {
        $check = ($this->makeCheck)();

        $this->get(route('filament.teacher.exams.print', ['exam' => $check, 'answers' => 'omr']))->assertOk()->assertSee('data-omr-sheet', escape: false);
        $this->get(route('filament.teacher.exams.print', ['exam' => $check, 'answers' => 'key']))->assertOk();
        $this->get(route('filament.teacher.exams.print', $check))->assertNotFound();
        $this->get(route('filament.teacher.exams.print', ['exam' => $check, 'answers' => 1]))->assertNotFound();
    });

    it('opens on its OMR page, with the sheet, the key and the results', function () {
        $check = ($this->makeCheck)();

        $this->get(route('filament.teacher.exams.omr-scan', $check))
            ->assertOk()
            ->assertSee(route('filament.teacher.exams.print', ['exam' => $check, 'answers' => 'omr']), escape: false)
            ->assertSee(route('filament.teacher.exams.print', ['exam' => $check, 'answers' => 'key']), escape: false)
            ->assertSee(OmrSheet::for($check)->pageCode(0));
    });
});

describe('a scanned sheet', function () {
    it('is marked against the key, with the check\'s marks and negative marking', function () {
        $check = ($this->makeCheck)(['marks' => 2, 'negative_marks' => 0.5]);

        ($this->save)($check, [
            'answers' => ['k1' => 0, 'k2' => 2, 'k3' => 0, 'k4' => 'several', 'k5' => null],
        ])->assertOk()->assertJson(['score' => 3, 'out_of' => 10]);

        $answers = ExamAttempt::sole()->answers()->get()->keyBy(fn (AttemptAnswer $answer): string => $answer->key());

        expect($answers->keys()->sort()->values()->all())->toBe(['k1', 'k2', 'k3', 'k4'])
            ->and($answers['k1']->student_answer)->toBe('A')
            ->and($answers['k1']->is_correct)->toBeTrue()
            ->and((float) $answers['k3']->obtained_marks)->toBe(-0.5)
            ->and($answers['k4']->student_answer)->toBe(OmrSheet::SEVERAL_FILLED);
    });

    it('shows on the results, sheet against key, and in the CSV', function () {
        $check = ($this->makeCheck)();
        ($this->save)($check, ['roll' => '7', 'name' => 'Karim', 'answers' => ['k1' => 0, 'k2' => 1]])->assertOk();
        Filament::setCurrentPanel('teacher');

        $sheet = livewire(ExamResults::class, ['record' => $check->id])
            ->assertSee('Karim')
            ->assertActionHidden('questionAnalysis')
            ->mountAction('viewAnswers', ['attempt' => ExamAttempt::sole()->id])
            ->instance()
            ->getMountedAction()
            ->getModalContent()
            ->render();

        // Question 2's right answer is C; B was filled in.
        expect(substr_count($sheet, 'qb-answer-key-bubble--wrong'))->toBe(1)
            ->and(substr_count($sheet, 'qb-answer-key-bubble--correct'))->toBe(5);

        $csv = $this->get(route('filament.teacher.exams.results.csv', $check))->assertOk()->streamedContent();

        expect($csv)->toStartWith("\u{FEFF}")
            ->toContain('Karim', 'Roll 7 · OMR', '1,5');
    });

    it('can no longer change the key once a sheet is saved — until the check is cancelled', function () {
        $check = ($this->makeCheck)();
        ($this->save)($check, ['answers' => ['k1' => 0]])->assertOk();

        expect(fn () => app(OmrCheckService::class)->save($this->teacher, ['title' => 'x', 'questions' => 1, 'options' => 4, 'marks' => 1, 'omr_identity' => 'roll', 'answers' => [1]], $check))
            ->toThrow(ValidationException::class);

        Filament::setCurrentPanel('teacher');
        livewire(ListOmrChecks::class)->assertActionHidden(TestAction::make('editKey')->table($check));

        $check->cancel();

        expect(app(OmrCheckService::class)->save($this->teacher, ['title' => 'x', 'questions' => 1, 'options' => 4, 'marks' => 1, 'omr_identity' => 'roll', 'answers' => [1]], $check->fresh())->answerKey()['answers'])->toBe([1]);
    });

    it('is not saved by another teacher', function () {
        $check = ($this->makeCheck)();

        $this->actingAs(tap(User::factory()->teacher()->create(), fn (User $other) => subscribeToEverything($other)));

        ($this->save)($check, [])->assertForbidden();
        $this->get(route('filament.teacher.exams.omr-scan', $check))->assertForbidden();
    });
});

describe('by registration number', function () {
    it('reads the registration number grid, and saves a number once', function () {
        $check = ($this->makeCheck)(['omr_identity' => OmrIdentity::Registration->value]);
        $layout = OmrSheet::for($check)->scannerLayout();

        expect($layout['roll'])->toHaveCount(OmrSheet::grids()['registration']['columns'])
            ->and($layout['roll'])->toBe(OmrSheet::gridColumns(OmrSheet::grids()['registration']));

        ($this->save)($check, ['roll' => '০০৪৫৬৭', 'answers' => ['k1' => 0]])->assertOk();
        ($this->save)($check, ['roll' => '4567'])->assertUnprocessable()->assertJsonValidationErrors('roll');

        $attempt = ExamAttempt::sole();

        expect($attempt->registration_no)->toBe('4567')
            ->and($attempt->school_roll)->toBeNull()
            ->and(app(ExamResultSheet::class)->rowsFor($check)->sole()['contact'])->toBe('Registration 4567 · OMR');
    });

    it('can be chosen for any printed exam on its OMR page, until a sheet is saved', function () {
        $check = ($this->makeCheck)();
        $before = OmrSheet::for($check)->pageCode(0);

        $this->post(route('filament.teacher.exams.omr-identity', $check), ['identity' => 'registration'])
            ->assertRedirect(route('filament.teacher.exams.omr-scan', $check));

        expect($check->fresh()->omr_identity)->toBe(OmrIdentity::Registration)
            ->and(OmrSheet::for($check->fresh())->pageCode(0))->not->toBe($before);

        ($this->save)($check, ['roll' => '99'])->assertOk();

        $this->post(route('filament.teacher.exams.omr-identity', $check), ['identity' => 'roll'])->assertSessionHasErrors('identity');

        expect($check->fresh()->omr_identity)->toBe(OmrIdentity::Registration);
    });
});

describe('a School', function () {
    beforeEach(function () {
        Cache::flush();
        $this->school = User::factory()->school()->googleAuthenticated()->create();
        SchoolProfile::factory()->connected('https://school.test')->create(['user_id' => $this->school->id]);
        subscribeToEverything($this->school);
        /** The school software: its classes, its exams for class 9, and the list of exam 5's subject 7. */
        $this->students = [];
        Http::fake(fn ($request) => Http::response(match (true) {
            str_contains($request->url(), '/classes/9/exams') => ['exams' => [['id' => 5, 'name' => 'Half yearly', 'is_published' => false, 'subjects' => [['id' => 7, 'name' => 'Physics', 'name_bn' => null, 'mcq_total' => 5]]]]],
            str_contains($request->url(), '/exams/5/subjects/7/students') => ['mcq_total' => 5, 'is_published' => false, 'students' => array_map(fn (array $student): array => [...$student, 'section_name' => null, 'mcq_marks' => null, 'is_absent' => false, 'has_other_marks' => false], $this->students)],
            default => ['classes' => [['id' => 9, 'name' => 'Class Nine', 'name_bn' => null, 'sections' => []]]],
        }));
        $this->actingAs($this->school);

        $this->check = ($this->makeCheck)(['omr_identity' => OmrIdentity::Registration->value], $this->school);
        $this->schoolSave = fn (string $registration) => $this->postJson(
            route('filament.school.exams.omr-scan.store', $this->check),
            ['roll' => $registration, 'class_id' => 9, 'answers' => ['k1' => 0]],
        )->assertOk();
    });

    it('makes OMR checks too', function () {
        Filament::setCurrentPanel('school');

        livewire(ListOmrChecks::class)->assertCanSeeTableRecords([$this->check]);
    });

    it('matches the sheets to its students by registration number, and shows the ones it can\'t as failed', function () {
        ($this->schoolSave)('4567');
        ($this->schoolSave)('9999');

        $this->students = $students = [
            ['id' => 1, 'roll' => '15', 'registration' => '4567', 'name' => 'Karim', 'section_id' => null],
            ['id' => 2, 'roll' => '16', 'registration' => '1111', 'name' => 'Rahim', 'section_id' => null],
            ['id' => 3, 'roll' => '17', 'registration' => null, 'name' => 'Salma', 'section_id' => null],
        ];
        $match = app(SchoolResultMatcher::class)->match($this->check, 9, $students);

        expect(array_column($match['rows'], 'status'))->toBe([SchoolResultMatcher::MATCHED, SchoolResultMatcher::ABSENT, SchoolResultMatcher::NO_REGISTRATION])
            ->and($match['unmatched']->sole()->registration_no)->toBe('9999');

        Filament::setCurrentPanel('school');

        $page = livewire(SendToSchoolResults::class, ['exam' => $this->check->id])
            ->set('classId', 9)
            ->set('schoolExamId', 5)
            ->set('subjectId', 7)
            ->call('loadSheet')
            ->assertSee(__('No registration number in the school software'))
            ->assertSee('9999');

        expect(collect($page->instance()->marksToSend())->pluck('student_id')->all())->toBe([1, 2]);
    });
});
