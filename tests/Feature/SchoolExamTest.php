<?php

use App\Enums\ExamDeliveryMode;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\ExamResultSheet;
use App\Services\TeacherExamBuilder;
use App\Support\SchoolConnectionSignature;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Cache::flush();

    $this->school = User::factory()->school()->googleAuthenticated()->create();
    $this->profile = SchoolProfile::factory()->connected('https://school.test')->create(['user_id' => $this->school->id]);
    subscribeToEverything($this->school);

    // The school's software: Class Nine with sections A and B, Class Six without.
    $this->roster = ['classes' => [
        ['id' => 6, 'name' => 'Class Six', 'name_bn' => 'ষষ্ঠ শ্রেণি', 'sections' => []],
        ['id' => 9, 'name' => 'Class Nine', 'name_bn' => null, 'sections' => [['id' => 91, 'name' => 'A'], ['id' => 92, 'name' => 'B']]],
    ]];
    // One fake for the whole test: flip softwareDown to have it fail.
    $this->softwareDown = false;
    Http::fake(fn () => $this->softwareDown ? Http::response('down', 500) : Http::response($this->roster));

    $classSubject = ClassSubject::factory()->create();
    $this->question = Question::factory()->approved()->for(Chapter::factory()->create(['class_subject_id' => $classSubject->id]))->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 1,
    ]);

    // The School sets an online exam the way a teacher does.
    $this->exam = app(TeacherExamBuilder::class)->build($this->school, $classSubject, ExamDeliveryMode::Online, [$this->question->id], 'Class test', 20);
    $this->exam->publish();

    $this->start = fn (array $fields) => $this->post("/exam/{$this->exam->share_token}/school-start", [
        'class_id' => 9, 'section_id' => 91, 'roll' => '15', 'name' => 'Rahim Uddin', ...$fields,
    ]);
});

it('builds and lists the school\'s exams with the teacher\'s own pages', function () {
    $this->actingAs($this->school);

    $this->get('/school/select-questions')->assertOk();
    $this->get('/school/exams')->assertOk()->assertSee('Class test');

    expect($this->exam->refresh()->isSchoolExam())->toBeTrue()
        ->and(Exam::factory()->create()->isSchoolExam())->toBeFalse();
});

it('shows the school\'s own form on the share link, with its classes and sections', function () {
    $this->get("/exam/{$this->exam->share_token}")
        ->assertOk()
        ->assertSee(['Class Nine', 'Class Six', __('Roll')])
        ->assertDontSee(__('Login to attempt'))
        ->assertDontSee(__('Continue as guest'));

    // Asked for the list signed with the School's secret.
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://school.test/api/question-bank/classes'
        && $request->header(SchoolConnectionSignature::CODE_HEADER)[0] === $this->profile->connection_code
        && SchoolConnectionSignature::verify($this->profile->connection_secret, $request->header('X-Timestamp')[0], '', $request->header('X-Signature')[0]));
});

it('lets a student start with class, section, roll and name, and carry on later', function () {
    ($this->start)([])->assertRedirect();

    $attempt = ExamAttempt::query()->sole();

    expect($attempt)->toMatchArray([
        'is_guest' => true,
        'student_id' => null,
        'guest_name' => 'Rahim Uddin',
        'school_class_id' => 9,
        'school_class_name' => 'Class Nine',
        'school_section_id' => 91,
        'school_section_name' => 'A',
        'school_roll' => '15',
    ]);

    $this->get(route('guest-exam.take', $attempt))->assertOk();

    // The same student again — another device, a roll written "০১৫".
    ($this->start)(['roll' => '০১৫', 'name' => 'rahim  uddin'])->assertRedirect(route('guest-exam.take', $attempt));
    expect(ExamAttempt::query()->count())->toBe(1);
});

it('gives someone else under a taken roll an attempt of their own', function () {
    ($this->start)([]);
    ($this->start)(['name' => 'Karim']);

    expect(ExamAttempt::query()->pluck('guest_name')->all())->toBe(['Rahim Uddin', 'Karim']);
});

it('needs a class of the school, and a section where the class has them', function () {
    ($this->start)(['class_id' => 7])->assertSessionHasErrors('class_id');
    ($this->start)(['section_id' => null])->assertSessionHasErrors('class_id');
    ($this->start)(['section_id' => 61])->assertSessionHasErrors('class_id');

    // Class Six has no sections.
    ($this->start)(['class_id' => 6, 'section_id' => null])->assertRedirect();

    expect(ExamAttempt::query()->sole()->school_section_id)->toBeNull();
});

it('finds a submitted result by class, section and roll — the first one', function () {
    ($this->start)([]);
    ExamAttempt::query()->sole()->submitAndAutoGrade();
    ($this->start)(['name' => 'Karim']);

    $this->post("/exam/{$this->exam->share_token}/school-result", ['class_id' => 9, 'section_id' => 91, 'roll' => '15'])
        ->assertOk()
        ->assertSee('Rahim Uddin');

    $this->post("/exam/{$this->exam->share_token}/school-result", ['class_id' => 9, 'section_id' => 92, 'roll' => '15'])
        ->assertSessionHasErrors('result');
});

it('has no login or guest way into a school\'s exam', function () {
    $this->post("/exam/{$this->exam->share_token}/start", ['guest_name' => 'X', 'guest_contact' => '017'])->assertNotFound();
    $this->post("/exam/{$this->exam->share_token}/result", ['guest_name' => 'X', 'guest_contact' => '017'])->assertNotFound();
    $this->get("/exam/{$this->exam->share_token}/join")->assertRedirect(route('guest-exam.show', $this->exam->share_token));

    // And no school form on anyone else's exam.
    $teacherExam = Exam::factory()->published()->create();
    $this->post("/exam/{$teacherExam->share_token}/school-start", ['class_id' => 9, 'roll' => '1', 'name' => 'X'])->assertNotFound();
});

it('asks the school\'s software for its classes once in a while, and keeps the last list', function () {
    $this->get("/exam/{$this->exam->share_token}")->assertOk();
    $this->get("/exam/{$this->exam->share_token}")->assertOk();
    Http::assertSentCount(1);

    // Ten minutes on, the software is down: the last list still works.
    $this->travel(11)->minutes();
    $this->softwareDown = true;

    $this->get("/exam/{$this->exam->share_token}")->assertOk()->assertSee('Class Nine');
});

it('says so when the school\'s classes cannot be had at all', function () {
    $this->softwareDown = true;

    $this->get("/exam/{$this->exam->share_token}")
        ->assertOk()
        ->assertSee(__('The class list of the school could not be loaded just now, so the exam cannot be started. Please try again in a few minutes, or tell your school.'));
});

it('names a school student by class, section and roll on the result sheet', function () {
    ($this->start)([]);
    ExamAttempt::query()->sole()->submitAndAutoGrade();

    $row = app(ExamResultSheet::class)->rowsFor($this->exam->refresh())->first();

    expect($row['attempt']->participantContact())->toBe('Class Nine · A · '.__('Roll :roll', ['roll' => '15']));
});

it('writes every roll one way', function (string $typed, string $stored) {
    expect(ExamAttempt::normalizeRoll($typed))->toBe($stored);
})->with([
    ['15', '15'], [' 015 ', '15'], ['০১৫', '15'], ['0', '0'], ['12a', '12A'],
]);
