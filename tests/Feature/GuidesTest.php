<?php

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GuideCategory;
use App\Enums\GuidePage;
use App\Enums\UserRole;
use App\Filament\Resources\Guides\Pages\ManageGuides;
use App\Filament\Student\Pages\MySubscription as StudentSubscription;
use App\Filament\Support\Pages\HelpCenter;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Guide;
use App\Models\Question;
use App\Models\User;
use App\Services\OnboardingChecklist;
use Database\Seeders\GuideSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->student = User::factory()->student()->create();
    $this->teacher = User::factory()->teacher()->create();
});

it('shows a guide only to its roles while it is on, in the viewer\'s language', function () {
    $forStudents = Guide::factory()->forRoles(UserRole::Student)->create(['title_bn' => 'প্ল্যান কেনা', 'title_en' => 'Buying a plan']);
    $banglaOnly = Guide::factory()->forRoles(UserRole::Student)->create(['title_bn' => 'শুধু বাংলা', 'title_en' => null, 'body_en' => null]);
    Guide::factory()->forRoles(UserRole::Student)->create(['is_active' => false]);
    Guide::factory()->forRoles(UserRole::Teacher)->create();

    expect(Guide::for($this->student)->pluck('id')->all())->toEqualCanonicalizing([$forStudents->id, $banglaOnly->id]);

    app()->setLocale('bn');
    expect($forStudents->title())->toBe('প্ল্যান কেনা');

    app()->setLocale('en');
    expect($forStudents->title())->toBe('Buying a plan')
        ->and($banglaOnly->title())->toBe('শুধু বাংলা')
        ->and($banglaOnly->bodyHtml())->toContain('বাংলায় ধাপগুলো');
});

it('turns a YouTube link into a video to embed, and ignores anything else', function (string $link, ?string $embed) {
    expect(Guide::factory()->make(['video_url' => $link])->videoEmbedUrl())->toBe($embed);
})->with([
    'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'short link' => ['https://youtu.be/dQw4w9WgXcQ?t=5', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'not youtube' => ['https://example.com/video.mp4', null],
]);

it('knows which help spot a panel page is, by its own class or the shared one', function () {
    expect(GuidePage::forScopes([StudentSubscription::class]))->toBe(GuidePage::Subscription)
        ->and(GuidePage::forScopes([SelectQuestions::class]))->toBe(GuidePage::BuildExam)
        ->and(GuidePage::forScopes(['App\Filament\Pages\Dashboard']))->toBeNull();
});

it('lists, searches and opens a role\'s guides on the Help page', function () {
    Filament::setCurrentPanel('student');
    app()->setLocale('en');
    $this->actingAs($this->student);
    $buying = Guide::factory()->forRoles(UserRole::Student)->create(['category' => GuideCategory::Subscription, 'title_en' => 'Buying a plan', 'body_en' => '<p>Pay with bKash.</p>']);
    $practice = Guide::factory()->forRoles(UserRole::Student)->create(['category' => GuideCategory::Practice, 'title_en' => 'Practice exams']);
    $teachers = Guide::factory()->forRoles(UserRole::Teacher)->create(['title_en' => 'Building an exam']);

    livewire(HelpCenter::class)
        ->assertSee('Buying a plan')->assertSee('Practice exams')->assertDontSee('Building an exam')
        ->set('search', 'bKash')
        ->assertSee('Buying a plan')->assertDontSee('Practice exams')
        ->call('open', $buying->id)
        ->assertSee('Pay with bKash.')
        ->call('open', $teachers->id)
        ->assertDontSee('Building an exam');

    $this->get('/student/help')->assertOk();
});

it('puts a "How do I…?" button on a page that has guides for the viewer\'s role', function () {
    app()->setLocale('en');
    $this->actingAs($this->student);

    $this->get('/student/my-subscription')->assertOk()->assertDontSee('How do I…?');

    Guide::factory()->forRoles(UserRole::Student)->onPage(GuidePage::Subscription)->create(['title_en' => 'Buying a plan', 'body_en' => '<p>Send the money first.</p>']);
    Guide::factory()->forRoles(UserRole::Teacher)->onPage(GuidePage::Subscription)->create(['body_en' => '<p>Teachers only.</p>']);

    $this->get('/student/my-subscription')->assertOk()->assertSee('How do I…?')->assertSee('Send the money first.')->assertDontSee('Teachers only.');
});

it('ticks off a student\'s "Get started" steps as they are done, then goes away', function () {
    $checklist = app(OnboardingChecklist::class);

    expect(collect($checklist->for($this->student))->pluck('done')->all())->toBe([false, false, false]);

    $this->student->update(['academic_class_id' => AcademicClass::factory()->create()->id]);
    $practice = Exam::factory()->selfPractice()->create(['created_by' => $this->student->id]);
    ExamAttempt::factory()->submitted()->create(['exam_id' => $practice->id, 'student_id' => $this->student->id]);

    expect(collect($checklist->for($this->student->fresh()))->pluck('done')->all())->toBe([true, true, false]);

    subscribeToEverything($this->student);
    expect($checklist->for($this->student->fresh()))->toBeNull();
});

it('ticks off a teacher\'s steps from what they have done', function () {
    $this->actingAs($this->teacher);
    Question::factory()->create(['created_by' => $this->teacher->id]);
    Exam::factory()->create(['created_by' => $this->teacher->id, 'exam_type' => ExamType::TeacherExam, 'status' => ExamStatus::Draft]);

    expect(collect(app(OnboardingChecklist::class)->for($this->teacher))->pluck('done')->all())->toBe([false, true, true, false]);
});

it('shows "Get started" on the dashboard until the user closes it or an Admin switches it off', function () {
    app()->setLocale('en');
    $this->actingAs($this->student);

    $this->get('/student')->assertOk()->assertSee('Get started')->assertSee('Choose your class');

    $this->postJson(route('onboarding.dismiss'))->assertOk();
    $this->get('/student')->assertOk()->assertDontSee('Choose your class');

    $this->actingAs($this->teacher);
    BillingSetting::create(['onboarding_checklist_enabled' => false]);
    expect(app(OnboardingChecklist::class)->for($this->teacher))->toBeNull();
});

it('lets an Admin write a guide in two languages for some roles and pages', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManageGuides::class)
        ->callAction('create', [
            'title_bn' => 'প্রশ্ন লেখা',
            'body_bn' => '<p>ধাপ ১</p>',
            'title_en' => 'Writing a question',
            'body_en' => '<p>Step 1</p>',
            'category' => GuideCategory::Questions->value,
            'target_roles' => [UserRole::Teacher->value, UserRole::Staff->value],
            'page_keys' => [GuidePage::WriteQuestions->value],
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Guide::onPage($this->teacher, GuidePage::WriteQuestions)->sole()->title_bn)->toBe('প্রশ্ন লেখা');
});

it('seeds the first guides once, for every role, in both languages', function () {
    $this->seed(GuideSeeder::class);
    $count = Guide::count();
    $this->seed(GuideSeeder::class);

    expect(Guide::count())->toBe($count)
        ->and($count)->toBeGreaterThan(10)
        ->and(Guide::whereNull('title_en')->count())->toBe(0);

    foreach (Guide::AUDIENCE_ROLES as $role) {
        expect(Guide::query()->whereJsonContains('target_roles', $role->value)->exists())->toBeTrue();
    }
});
