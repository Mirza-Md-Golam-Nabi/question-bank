<?php

use App\Filament\Resources\Questions\Pages\BrowseChapters;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a chapter scoped to the current class and subject', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);

    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])
        ->callAction('createChapter', data: ['name' => 'Algebra', 'order_index' => 1])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('chapters', ['class_subject_id' => $classSubject->id, 'name' => 'Algebra']);
});

it('shows how many questions each chapter has', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);
    $chapter = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Algebra', 'order_index' => 1]);

    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])
        ->assertSee($chapter->name)
        ->assertSee('0 Questions');
});

it('edits a chapter', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);
    $chapter = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Old name', 'order_index' => 1]);

    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])
        ->callAction('editChapter', data: ['name' => 'New name', 'order_index' => 2], arguments: ['chapter' => $chapter->id])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('chapters', ['id' => $chapter->id, 'name' => 'New name']);
});

it('shifts later chapters down when a new chapter is inserted at their order', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);

    $chapter1 = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Chapter 1', 'order_index' => 1]);
    $chapter2 = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Chapter 2', 'order_index' => 2]);
    $chapter3 = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Chapter 3', 'order_index' => 3]);

    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])
        ->callAction('createChapter', data: ['name' => 'Chapter 1.5', 'order_index' => 2])
        ->assertHasNoActionErrors();

    expect($chapter1->refresh()->order_index)->toBe(1);
    expect($chapter2->refresh()->order_index)->toBe(3);
    expect($chapter3->refresh()->order_index)->toBe(4);
    $this->assertDatabaseHas('chapters', ['name' => 'Chapter 1.5', 'order_index' => 2]);
});

it('shifts the affected range when a chapter moves later in the order', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);

    $chapter1 = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Chapter 1', 'order_index' => 1]);
    $chapter2 = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Chapter 2', 'order_index' => 2]);
    $chapter3 = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Chapter 3', 'order_index' => 3]);

    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])
        ->callAction('editChapter', data: ['name' => $chapter1->name, 'order_index' => 3], arguments: ['chapter' => $chapter1->id])
        ->assertHasNoActionErrors();

    expect($chapter1->refresh()->order_index)->toBe(3);
    expect($chapter2->refresh()->order_index)->toBe(1);
    expect($chapter3->refresh()->order_index)->toBe(2);
});

it('keeps the add chapter modal open with the next display order when adding another', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);

    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])
        ->callAction('createChapter', data: ['name' => 'Algebra', 'order_index' => 1], arguments: ['another' => true])
        ->assertHasNoActionErrors()
        ->assertActionHalted('createChapter')
        ->assertSchemaStateSet(['name' => null, 'order_index' => 2])
        ->fillForm(['name' => 'Geometry'])
        ->callMountedAction(['another' => true])
        ->assertHasNoActionErrors()
        ->assertSchemaStateSet(['name' => null, 'order_index' => 3]);

    expect(Chapter::where('class_subject_id', $classSubject->id)->orderBy('order_index')->pluck('order_index', 'name')->all())
        ->toBe(['Algebra' => 1, 'Geometry' => 2]);
});
