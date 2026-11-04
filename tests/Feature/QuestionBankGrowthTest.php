<?php

use App\Enums\PaperSourceType;
use App\Filament\Support\Widgets\QuestionBankGrowthWidget;
use App\Models\AcademicClass;
use App\Models\BoardCqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionBankGrowth;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(now()->setTime(12, 0));
});

/**
 * @return array<string, int> Each day's count keyed by its date.
 */
function growthPerDay(): array
{
    return collect(app(QuestionBankGrowth::class)->summary()['days'])->pluck('count', 'date')->all();
}

it('counts approved MCQs, CQs and every question of an approved past paper on the day they were approved', function () {
    $noon = now();
    $this->travelTo($noon->copy()->subDays(2));
    Question::factory()->approved()->create();
    $this->travelTo($noon);

    Question::factory()->approved()->count(2)->create();
    Question::factory()->approved()->cq()->create();

    $paper = BoardQuestionPaper::factory()->approved()->withMcqs(3)->create();
    BoardCqQuestion::factory()->create(['board_question_paper_id' => $paper->id]);

    $days = growthPerDay();

    expect($days)->toHaveCount(QuestionBankGrowth::DAYS)
        ->and($days[today()->toDateString()])->toBe(7)
        ->and($days[today()->subDays(2)->toDateString()])->toBe(1)
        ->and(array_sum($days))->toBe(8);
});

it('leaves out what is pending, rejected, a School\'s own, a corrected version or a paper not approved', function () {
    $original = Question::factory()->approved()->create();

    Question::factory()->create(['created_by' => User::factory()->teacher()]);
    Question::factory()->rejected()->create();
    Question::factory()->approved()->create(['is_private' => true]);
    Question::factory()->approved()->create(['parent_id' => $original->id, 'version' => 2]);
    BoardQuestionPaper::factory()->withMcqs(4)->create();

    expect(growthPerDay()[today()->toDateString()])->toBe(1);
});

it('stamps approved_at when an Admin approves and clears it on rejection', function () {
    $admin = User::factory()->admin()->create();
    $paper = BoardQuestionPaper::factory()->withMcqs(2)->create();

    expect($paper->approved_at)->toBeNull();

    $paper->approve($admin);
    expect($paper->fresh()->approved_at->isToday())->toBeTrue();

    $paper->reject($admin, 'Wrong year');
    expect($paper->fresh()->approved_at)->toBeNull();
});

it('names the classes and the class-less paper kinds of the last day anything joined', function () {
    $nine = AcademicClass::factory()->create(['name' => 'Class 9']);
    $chapter = Chapter::factory()->create(['class_subject_id' => ClassSubject::factory()->create(['academic_class_id' => $nine->id])->id]);
    Question::factory()->approved()->create(['chapter_id' => $chapter->id]);
    BoardQuestionPaper::factory()->approved()->admission(PaperSourceType::Medical)->withMcqs(1)->create();

    $this->travelTo(now()->addDay());

    $summary = app(QuestionBankGrowth::class)->summary();

    expect($summary['classes'])->toBe(['Class 9'])
        ->and($summary['source_types'])->toBe([PaperSourceType::Medical->value])
        ->and($summary['last_added_at'])->not->toBeNull();
});

it('is on the dashboard of every panel', function (string $panel) {
    expect(Filament::getPanel($panel)->getWidgets())->toContain(QuestionBankGrowthWidget::class);
})->with(['admin', 'teacher', 'staff', 'student', 'school']);

it('shows the last days and the classes, in Bangla digits for a Bangla reader', function () {
    $this->actingAs(User::factory()->student()->create());
    app()->setLocale('bn');

    Question::factory()->approved()->count(3)->create();

    livewire(QuestionBankGrowthWidget::class)
        ->assertOk()
        ->assertSee('LIVE')
        ->assertSee(__('Database update!'))
        ->assertSee('৩');
});
