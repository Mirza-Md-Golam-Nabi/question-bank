<?php

use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\Questions\QuestionResource;
use App\Filament\Teacher\Widgets\TeacherQuickActionsWidget;
use App\Filament\Teacher\Widgets\TeacherRecentExamsWidget;
use App\Filament\Teacher\Widgets\TeacherRecentQuestionsWidget;
use App\Filament\Teacher\Widgets\TeacherStatsOverviewWidget;
use App\Filament\Teacher\Widgets\TeacherSubscriptionWidget;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets an active teacher load the dashboard, which mounts every widget', function () {
    $teacher = User::factory()->teacher()->create();

    $response = $this->actingAs($teacher)->get('/teacher');

    $response->assertOk();
});

it('shows an enabled add-question link for an active teacher', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    $html = livewire(TeacherQuickActionsWidget::class)->html();

    expect($html)->toContain(QuestionResource::getUrl('index', panel: 'teacher'));
    expect($html)->toContain(ExamResource::getUrl('create', panel: 'teacher'));
});

it('hides the add-question link behind a disabled button for a suspended teacher', function () {
    $teacher = User::factory()->teacher()->suspended()->create();
    $this->actingAs($teacher);

    $html = livewire(TeacherQuickActionsWidget::class)->html();

    expect($html)->not->toContain(QuestionResource::getUrl('index', panel: 'teacher'));
});

it('summarizes a teacher\'s own question and exam counts — never another teacher\'s', function () {
    $teacher = User::factory()->teacher()->create();
    $otherTeacher = User::factory()->teacher()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($teacher);
    Question::factory()->count(2)->create(['created_by' => $teacher->id]);
    Exam::factory()->create(['created_by' => $teacher->id]);
    Exam::factory()->published()->create(['created_by' => $teacher->id]);

    Question::factory()->create(['created_by' => $otherTeacher->id]);
    Exam::factory()->create(['created_by' => $otherTeacher->id]);

    $this->actingAs($admin);
    Question::factory()->create(['created_by' => $teacher->id])->approve($admin);
    Question::factory()->rejected()->create(['created_by' => $teacher->id]);

    $this->actingAs($teacher);

    livewire(TeacherStatsOverviewWidget::class)
        ->assertSee('Pending 2')
        ->assertSee('Approved 1')
        ->assertSee('Rejected 1')
        ->assertSee('50.0%')
        ->assertSee('Draft 1')
        ->assertSee('Published 1');
});

it('lists a teacher\'s own recent questions with the rejection reason for rejected ones', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    Question::factory()->rejected()->create([
        'created_by' => $teacher->id,
        'rejection_reason' => 'তথ্যে ভুল আছে',
    ]);

    livewire(TeacherRecentQuestionsWidget::class)
        ->assertSee('তথ্যে ভুল আছে');
});

it('lists a teacher\'s own recent exams with a copyable share link', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    $exam = Exam::factory()->published()->create([
        'created_by' => $teacher->id,
        'title' => 'মধ্যবর্তী পরীক্ষা',
    ]);

    $html = livewire(TeacherRecentExamsWidget::class)
        ->assertSee('মধ্যবর্তী পরীক্ষা')
        ->html();

    // The link is there to be copied, but only an icon is on show.
    expect($html)->toContain('fi-copyable')
        ->and($html)->toContain($exam->share_token)
        ->and(strip_tags($html))->not->toContain($exam->share_token);
});

it('shows a subject by its short name on the recent questions and exams, or else its name in the active language', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    $withShortName = Subject::factory()->create(['name' => 'Information Technology', 'name_bn' => 'তথ্য প্রযুক্তি', 'short_name' => 'ICT']);
    $withoutShortName = Subject::factory()->create(['name' => 'Physics', 'name_bn' => 'পদার্থবিজ্ঞান', 'short_name' => null]);

    foreach ([$withShortName, $withoutShortName] as $subject) {
        $classSubject = ClassSubject::factory()->create(['subject_id' => $subject->id]);
        Question::factory()->for(Chapter::factory()->create(['class_subject_id' => $classSubject->id]))->create(['created_by' => $teacher->id]);
        Exam::factory()->create(['created_by' => $teacher->id, 'subject_id' => $subject->id]);
    }

    foreach ([TeacherRecentQuestionsWidget::class, TeacherRecentExamsWidget::class] as $widget) {
        app()->setLocale('en');
        expect(strip_tags(livewire($widget)->html()))
            ->toContain('ICT')
            ->toContain('Physics')
            ->not->toContain('পদার্থবিজ্ঞান');

        app()->setLocale('bn');
        expect(strip_tags(livewire($widget)->html()))
            ->toContain('ICT')
            ->toContain('পদার্থবিজ্ঞান');
    }
});

it('shows no active subscription message when the teacher has none', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    livewire(TeacherSubscriptionWidget::class)
        ->assertSee('No active subscription');
});

it('shows the active plan and monthly usage for a subscribed teacher', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Pro Plan', 'monthly_exam_limit' => 5]);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    $this->actingAs($teacher);
    Exam::factory()->create(['created_by' => $teacher->id]);

    livewire(TeacherSubscriptionWidget::class)
        ->assertSee('Pro Plan')
        ->assertSee('1 / 5');
});
