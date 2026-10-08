<?php

use App\Filament\Staff\Resources\Questions\Pages\ListQuestions as StaffListQuestions;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

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

    livewire(TeacherListQuestions::class)
        ->assertCanSeeTableRecords([$ownPending, $ownApproved, $ownRejected])
        // Other people's questions don't belong here, approved or not; the
        // approved pool is browsed on the Select questions page instead.
        ->assertCanNotSeeTableRecords([$othersPending, $othersApproved]);
});

it('still offers a teacher other people\'s approved questions on the Select questions page', function () {
    $teacherA = User::factory()->teacher()->create();
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
