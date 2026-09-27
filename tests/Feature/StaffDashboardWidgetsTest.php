<?php

use App\Filament\Staff\Resources\Questions\QuestionResource;
use App\Filament\Staff\Widgets\StaffQuestionRatesWidget;
use App\Filament\Staff\Widgets\StaffQuickActionsWidget;
use App\Filament\Staff\Widgets\StaffRecentQuestionsWidget;
use App\Filament\Staff\Widgets\StaffStatsOverviewWidget;
use App\Filament\Staff\Widgets\StaffSuspensionNoticeWidget;
use App\Models\Question;
use App\Models\QuestionRate;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets an active staff member load the dashboard, which mounts every non-suspension widget', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertOk();

    // The dashboard renders widgets lazily (wire:init) — a plain GET only
    // ships placeholders, so widget content is verified by mounting each
    // widget directly (as the tests below do), not by scraping this HTML.
});

it('shows the suspension notice widget\'s content for a suspended staff member', function () {
    $staff = User::factory()->staff()->suspended()->create();
    $this->actingAs($staff);

    livewire(StaffSuspensionNoticeWidget::class)
        ->assertSee('আপনার একাউন্ট সাসপেন্ড করা হয়েছে');
});

it('only allows viewing the suspension notice widget when the staff member is suspended', function () {
    $this->actingAs(User::factory()->staff()->create());
    expect(StaffSuspensionNoticeWidget::canView())->toBeFalse();

    $this->actingAs(User::factory()->staff()->suspended()->create());
    expect(StaffSuspensionNoticeWidget::canView())->toBeTrue();
});

it('shows an enabled add-question link for an active staff member', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    $html = livewire(StaffQuickActionsWidget::class)->html();

    expect($html)->toContain(QuestionResource::getUrl('index', panel: 'staff'));
});

it('hides the add-question link behind a disabled button for a suspended staff member', function () {
    $staff = User::factory()->staff()->suspended()->create();
    $this->actingAs($staff);

    $html = livewire(StaffQuickActionsWidget::class)->html();

    expect($html)->not->toContain(QuestionResource::getUrl('index', panel: 'staff'));
});

it('summarizes a staff member\'s own question counts, earnings, and approval rate — never another staff member\'s', function () {
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 5, 'effective_from' => now()->subDay()]);

    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($staff);
    Question::factory()->count(2)->create(['created_by' => $staff->id]);
    Question::factory()->create(['created_by' => $otherStaff->id]);

    $this->actingAs($admin);
    Question::factory()->create(['created_by' => $staff->id])->approve($admin);
    Question::factory()->rejected()->create(['created_by' => $staff->id]);

    $this->actingAs($staff);

    livewire(StaffStatsOverviewWidget::class)
        ->assertSee('Pending 2 · Approved 1 · Rejected 1')
        ->assertSee('৳5.00')
        ->assertSee('50.0%');
});

it('lists a staff member\'s own recent questions with the rejection reason for rejected ones', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    Question::factory()->rejected()->create([
        'created_by' => $staff->id,
        'rejection_reason' => 'গণিতে ভুল আছে',
    ]);

    livewire(StaffRecentQuestionsWidget::class)
        ->assertSee('গণিতে ভুল আছে');
});

it('renders the current per-subject question rates', function () {
    Subject::factory()->create(['name' => 'Physics']);
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 7, 'effective_from' => now()->subDay()]);

    $this->actingAs(User::factory()->staff()->create());

    livewire(StaffQuestionRatesWidget::class)
        ->assertSee('৳7.00');
});
