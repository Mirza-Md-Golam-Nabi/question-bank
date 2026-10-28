<?php

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Filament\Staff\Resources\Questions\Pages\ListQuestions as StaffListQuestions;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->chapter = Chapter::factory()->create();
});

it('lists only a teacher\'s own questions under "My questions", whatever their status', function () {
    $teacherA = User::factory()->teacher()->create();
    $teacherB = User::factory()->teacher()->create();

    $this->actingAs($teacherA);
    $ownPending = Question::factory()->for($this->chapter)->create(['created_by' => $teacherA->id]);
    $ownApproved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacherA->id]);
    $ownRejected = Question::factory()->rejected()->for($this->chapter)->create(['created_by' => $teacherA->id]);

    $this->actingAs($teacherB);
    $othersPending = Question::factory()->for($this->chapter)->create(['created_by' => $teacherB->id]);
    $othersApproved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacherB->id]);

    $this->actingAs($teacherA);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertCanSeeTableRecords([$ownPending, $ownApproved, $ownRejected])
        // Other people's questions don't belong here, approved or not; the
        // approved pool is browsed on the Select questions page instead.
        ->assertCanNotSeeTableRecords([$othersPending, $othersApproved]);
});

it('never finds other people\'s questions when a teacher searches "My questions"', function () {
    $teacherA = User::factory()->teacher()->create();
    $teacherB = User::factory()->teacher()->create();

    $this->actingAs($teacherA);
    $own = Question::factory()->for($this->chapter)->create(['created_by' => $teacherA->id, 'question_text' => '<p>Photosynthesis needs light</p>']);

    $this->actingAs($teacherB);
    $others = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacherB->id, 'question_text' => '<p>Photosynthesis makes glucose</p>']);

    $this->actingAs($teacherA);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->searchTable('Photosynthesis')
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$others]);
});

it('still offers a teacher other people\'s approved questions on the Select questions page', function () {
    $teacherA = User::factory()->teacher()->create();
    subscribeToEverything($teacherA);
    $teacherB = User::factory()->teacher()->create();

    $this->actingAs($teacherB);
    $othersApproved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacherB->id]);
    $othersPending = Question::factory()->for($this->chapter)->create(['created_by' => $teacherB->id]);

    $this->actingAs($teacherA);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $page = livewire(SelectQuestions::class)->set('data', [
        'exam_mode' => 'offline',
        'academic_class_id' => $this->chapter->classSubject->academic_class_id,
        'class_subject_id' => $this->chapter->class_subject_id,
        'chapter_id' => $this->chapter->id,
        'topic_id' => null,
        'question_type' => SelectQuestions::TYPE_BOTH,
    ]);

    expect($page->instance()->questions->pluck('id')->all())->toBe([$othersApproved->id]);
});

it('only lets staff see their own questions, never an approved pool from others', function () {
    $staffA = User::factory()->staff()->create();
    $staffB = User::factory()->staff()->create();

    $this->actingAs($staffA);
    $ownQuestion = Question::factory()->for($this->chapter)->create(['created_by' => $staffA->id]);

    $this->actingAs($staffB);
    $othersApproved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $staffB->id]);

    $this->actingAs($staffA);

    livewire(StaffListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertCanSeeTableRecords([$ownQuestion])
        ->assertCanNotSeeTableRecords([$othersApproved]);
});

it('shows a writer\'s own chapter questions whole as cards, and switches back to the table', function (string $role, string $panel, string $page) {
    $writer = User::factory()->state(['role' => $role, 'name' => 'Karim Writer'])->create();
    $other = User::factory()->teacher()->create();
    Filament::setCurrentPanel(Filament::getPanel($panel));

    $this->actingAs($writer);
    $own = Question::factory()->rejected()->for($this->chapter)->create([
        'created_by' => $writer->id,
        'question_text' => '<p>What is the capital of Bangladesh?</p>',
        'options' => [
            ['option' => 'Chattogram', 'image' => null, 'is_correct' => false],
            ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
        ],
        'explanation' => '<p>Dhaka has been the capital since 1971.</p>',
    ]);

    $this->actingAs($other);
    $others = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $other->id]);

    $this->actingAs($writer);

    livewire($page, ['chapter' => $this->chapter->id])
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$others])
        ->assertSee('Chattogram')
        ->assertSee('Dhaka has been the capital since 1971.')
        ->assertSee(QuestionStatus::Rejected->getLabel())
        ->assertSeeHtml('qb-question-option--correct')
        // Everything listed here is their own, so their name would only repeat.
        ->assertDontSee('Karim Writer')
        ->assertActionVisible('switchDisplay');

    Livewire::withQueryParams(['view' => 'table']);

    livewire($page, ['chapter' => $this->chapter->id])
        ->assertCanSeeTableRecords([$own])
        ->assertDontSee('Chattogram')
        ->assertTableColumnExists('created_at');
})->with([
    'teacher' => [UserRole::Teacher->value, 'teacher', TeacherListQuestions::class],
    'staff' => [UserRole::Staff->value, 'staff', StaffListQuestions::class],
    'school' => [UserRole::School->value, 'school', TeacherListQuestions::class],
]);
