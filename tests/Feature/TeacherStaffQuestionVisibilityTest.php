<?php

use App\Filament\Staff\Resources\Questions\Pages\ListQuestions as StaffListQuestions;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->chapter = Chapter::factory()->create();
});

it('lets a teacher see their own pending questions plus everyone else\'s approved pool, but not other teachers\' pending ones', function () {
    $teacherA = User::factory()->teacher()->create();
    $teacherB = User::factory()->teacher()->create();

    $this->actingAs($teacherA);
    $ownPending = Question::factory()->for($this->chapter)->create(['created_by' => $teacherA->id]);

    $this->actingAs($teacherB);
    $othersPending = Question::factory()->for($this->chapter)->create(['created_by' => $teacherB->id]);
    $othersApproved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacherB->id]);

    $this->actingAs($teacherA);

    livewire(TeacherListQuestions::class)
        ->assertCanSeeTableRecords([$ownPending, $othersApproved])
        ->assertCanNotSeeTableRecords([$othersPending]);
});

it('only lets staff see their own questions, never an approved pool from others', function () {
    $staffA = User::factory()->staff()->create();
    $staffB = User::factory()->staff()->create();

    $this->actingAs($staffA);
    $ownQuestion = Question::factory()->for($this->chapter)->create(['created_by' => $staffA->id]);

    $this->actingAs($staffB);
    $othersApproved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $staffB->id]);

    $this->actingAs($staffA);

    livewire(StaffListQuestions::class)
        ->assertCanSeeTableRecords([$ownQuestion])
        ->assertCanNotSeeTableRecords([$othersApproved]);
});
