<?php

use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion as TeacherEditQuestion;
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

it('soft-deletes a question, keeping the row but hiding it from normal listings', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $question = Question::factory()->approved()->for($this->chapter)->create();

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->callTableAction('delete', $question)
        ->assertHasNoTableActionErrors();

    $this->assertSoftDeleted($question);
    expect(Question::find($question->id))->toBeNull();
    expect(Question::withTrashed()->find($question->id))->not->toBeNull();
});

it('shows trashed questions via the Trashed filter and lets an admin restore one', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $question = Question::factory()->approved()->for($this->chapter)->create();
    $question->delete();

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertCanNotSeeTableRecords([$question])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$question])
        ->callTableAction('restore', $question)
        ->assertHasNoTableActionErrors();

    expect(Question::find($question->id))->not->toBeNull();
    expect($question->refresh()->deleted_at)->toBeNull();
});

it('lets an admin permanently delete a trashed question', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $question = Question::factory()->approved()->for($this->chapter)->create();
    $question->delete();

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->filterTable('trashed', true)
        ->callTableAction('forceDelete', $question)
        ->assertHasNoTableActionErrors();

    $this->assertModelMissing($question);
});

it('lets the owning teacher restore their own soft-deleted question but not force-delete it', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);
    $question = Question::factory()->for($this->chapter)->create(['created_by' => $teacher->id]);
    $question->delete();

    expect($teacher->can('restore', $question))->toBeTrue();
    expect($teacher->can('forceDelete', $question))->toBeFalse();
});

it('lets a teacher or staff member delete their own question only until it is approved', function (string $role) {
    $owner = User::factory()->{$role}()->create();
    $this->actingAs($owner);

    $pending = Question::factory()->for($this->chapter)->create(['created_by' => $owner->id]);
    $rejected = Question::factory()->rejected()->for($this->chapter)->create(['created_by' => $owner->id]);
    $approved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $owner->id]);

    expect($owner->can('delete', $pending))->toBeTrue()
        ->and($owner->can('delete', $rejected))->toBeTrue()
        ->and($owner->can('delete', $approved))->toBeFalse();

    expect(User::factory()->admin()->create()->can('delete', $approved))->toBeTrue();
})->with(['teacher', 'staff']);

it('hides the delete button of an approved question from its teacher, on the list and on the edit page', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    $pending = Question::factory()->for($this->chapter)->create(['created_by' => $teacher->id]);
    $approved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacher->id]);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertTableActionVisible('delete', $pending)
        ->assertTableActionHidden('delete', $approved);

    livewire(TeacherEditQuestion::class, ['record' => $pending->id])->assertActionVisible('delete');
    livewire(TeacherEditQuestion::class, ['record' => $approved->id])->assertActionHidden('delete');
});

it('leaves an approved question alone when a teacher bulk-deletes a selection', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);

    $pending = Question::factory()->for($this->chapter)->create(['created_by' => $teacher->id]);
    $approved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $teacher->id]);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->callTableBulkAction('delete', [$pending, $approved]);

    $this->assertSoftDeleted($pending);
    $this->assertNotSoftDeleted($approved);
});
