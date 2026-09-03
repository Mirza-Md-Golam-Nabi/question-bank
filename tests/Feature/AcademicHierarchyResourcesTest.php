<?php

use App\Filament\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Models\AcademicClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates an academic class from the question browser', function () {
    livewire(BrowseClasses::class)
        ->callAction('createClass', data: ['name' => 'Class 9', 'order_index' => 9])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('academic_classes', ['name' => 'Class 9', 'order_index' => 9]);
});

it('shows how many subjects are assigned on each class card', function () {
    $class = AcademicClass::factory()->create();
    $class->subjects()->attach(Subject::factory()->create(), ['order_index' => 1]);
    $class->subjects()->attach(Subject::factory()->create(), ['order_index' => 2]);

    livewire(BrowseClasses::class)
        ->assertSee($class->name)
        ->assertSee('2 Subjects');
});

it('edits an academic class', function () {
    $class = AcademicClass::factory()->create(['name' => 'Old name']);

    livewire(BrowseClasses::class)
        ->callAction('editClass', data: ['name' => 'New name', 'order_index' => 5], arguments: ['class' => $class->id])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('academic_classes', ['id' => $class->id, 'name' => 'New name']);
});

it('deletes an academic class', function () {
    $class = AcademicClass::factory()->create();

    livewire(BrowseClasses::class)
        ->callAction('deleteClass', arguments: ['class' => $class->id]);

    $this->assertModelMissing($class);
});

it('creates a subject', function () {
    livewire(ManageSubjects::class)
        ->callAction('create', data: ['name' => 'Physics'])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('subjects', ['name' => 'Physics']);
});

it('attaches a subject to a class from the subject browser', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();

    livewire(BrowseSubjects::class, ['class' => $class->id])
        ->callAction('attachSubject', data: ['subject_id' => $subject->id, 'order_index' => 1])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('class_subjects', [
        'academic_class_id' => $class->id,
        'subject_id' => $subject->id,
        'order_index' => 1,
    ]);
});

it('detaches a subject from a class', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $class->subjects()->attach($subject, ['order_index' => 1]);

    livewire(BrowseSubjects::class, ['class' => $class->id])
        ->callAction('detachSubject', arguments: [
            'classSubject' => $class->subjects()->where('subject_id', $subject->id)->first()->pivot->id,
        ]);

    $this->assertDatabaseMissing('class_subjects', [
        'academic_class_id' => $class->id,
        'subject_id' => $subject->id,
    ]);
});

it('shifts later classes down when a new class is inserted at their order', function () {
    $class1 = AcademicClass::factory()->create(['name' => 'Class 6', 'order_index' => 1]);
    $class2 = AcademicClass::factory()->create(['name' => 'Class 7', 'order_index' => 2]);
    $class3 = AcademicClass::factory()->create(['name' => 'Class 8', 'order_index' => 3]);

    livewire(BrowseClasses::class)
        ->callAction('createClass', data: ['name' => 'Class 6.5', 'order_index' => 2])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('academic_classes', ['name' => 'Class 6.5', 'order_index' => 2]);
    expect($class1->refresh()->order_index)->toBe(1);
    expect($class2->refresh()->order_index)->toBe(3);
    expect($class3->refresh()->order_index)->toBe(4);
});

it('shifts the affected range when an academic class moves earlier in the order', function () {
    $class1 = AcademicClass::factory()->create(['order_index' => 1]);
    $class2 = AcademicClass::factory()->create(['order_index' => 2]);
    $class3 = AcademicClass::factory()->create(['order_index' => 3]);
    $class4 = AcademicClass::factory()->create(['order_index' => 4]);

    livewire(BrowseClasses::class)
        ->callAction('editClass', data: ['name' => $class4->name, 'order_index' => 2], arguments: ['class' => $class4->id])
        ->assertHasNoActionErrors();

    expect($class1->refresh()->order_index)->toBe(1);
    expect($class2->refresh()->order_index)->toBe(3);
    expect($class3->refresh()->order_index)->toBe(4);
    expect($class4->refresh()->order_index)->toBe(2);
});

it('shifts the affected range when an academic class moves later in the order', function () {
    $class1 = AcademicClass::factory()->create(['order_index' => 1]);
    $class2 = AcademicClass::factory()->create(['order_index' => 2]);
    $class3 = AcademicClass::factory()->create(['order_index' => 3]);
    $class4 = AcademicClass::factory()->create(['order_index' => 4]);

    livewire(BrowseClasses::class)
        ->callAction('editClass', data: ['name' => $class1->name, 'order_index' => 3], arguments: ['class' => $class1->id])
        ->assertHasNoActionErrors();

    expect($class1->refresh()->order_index)->toBe(3);
    expect($class2->refresh()->order_index)->toBe(1);
    expect($class3->refresh()->order_index)->toBe(2);
    expect($class4->refresh()->order_index)->toBe(4);
});

it('shifts later subjects down when a subject is attached at an existing order', function () {
    $class = AcademicClass::factory()->create();
    $bangla = Subject::factory()->create(['name' => 'Bangla']);
    $english = Subject::factory()->create(['name' => 'English']);
    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $science = Subject::factory()->create(['name' => 'Science']);
    $religion = Subject::factory()->create(['name' => 'Religion']);
    $bgs = Subject::factory()->create(['name' => 'BGS']);

    $class->subjects()->attach($bangla, ['order_index' => 1]);
    $class->subjects()->attach($english, ['order_index' => 2]);
    $class->subjects()->attach($math, ['order_index' => 3]);
    $class->subjects()->attach($science, ['order_index' => 4]);
    $class->subjects()->attach($religion, ['order_index' => 5]);

    livewire(BrowseSubjects::class, ['class' => $class->id])
        ->callAction('attachSubject', data: ['subject_id' => $bgs->id, 'order_index' => 3])
        ->assertHasNoActionErrors();

    $pivotOrder = fn ($subject) => $class->subjects()->where('subject_id', $subject->id)->first()->pivot->order_index;

    expect($pivotOrder($bangla))->toBe(1);
    expect($pivotOrder($english))->toBe(2);
    expect($pivotOrder($bgs))->toBe(3);
    expect($pivotOrder($math))->toBe(4);
    expect($pivotOrder($science))->toBe(5);
    expect($pivotOrder($religion))->toBe(6);

    $mathPivotId = $class->subjects()->where('subject_id', $math->id)->first()->pivot->id;

    livewire(BrowseSubjects::class, ['class' => $class->id])
        ->callAction('editSubjectOrder', data: ['order_index' => 3], arguments: ['classSubject' => $mathPivotId])
        ->assertHasNoActionErrors();

    expect($pivotOrder($bangla))->toBe(1);
    expect($pivotOrder($english))->toBe(2);
    expect($pivotOrder($math))->toBe(3);
    expect($pivotOrder($bgs))->toBe(4);
    expect($pivotOrder($science))->toBe(5);
    expect($pivotOrder($religion))->toBe(6);
});

it('rejects assigning the same subject to the same class twice at the database level', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $class->subjects()->attach($subject, ['order_index' => 1]);

    expect(fn () => $class->subjects()->attach($subject, ['order_index' => 2]))
        ->toThrow(QueryException::class);
});
