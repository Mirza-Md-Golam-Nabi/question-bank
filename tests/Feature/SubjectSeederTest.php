<?php

use App\Models\AcademicClass;
use App\Models\ClassSubject;
use App\Models\Subject;
use Database\Seeders\AcademicClassSeeder;
use Database\Seeders\SubjectSeeder;

it('does not seed subjects or assign them to classes outside the local environment', function () {
    AcademicClass::factory()->create(['name' => 'Class 6']);

    $this->seed(SubjectSeeder::class);

    expect(Subject::count())->toBe(0)
        ->and(ClassSubject::count())->toBe(0);
});

it('seeds every subject with an english name, a bangla name and a unique short name', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed(SubjectSeeder::class);

    $subjects = Subject::all();

    expect($subjects)->toHaveCount(67)
        ->and($subjects->pluck('short_name')->unique())->toHaveCount(67)
        ->and($subjects->pluck('name_bn')->unique())->toHaveCount(67)
        ->and(Subject::where('name', 'Physics 1st Paper')->first())
        ->name_bn->toBe('পদার্থবিজ্ঞান ১ম পত্র')
        ->short_name->toBe('PHY-1');
});

it('fills in the bangla and short names of subjects that already exist', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $existing = Subject::factory()->create(['name' => 'Mathematics', 'name_bn' => null, 'short_name' => null]);

    $this->seed(SubjectSeeder::class);

    expect($existing->refresh())
        ->name_bn->toBe('গণিত')
        ->short_name->toBe('MATH')
        ->and(Subject::where('name', 'Mathematics')->count())->toBe(1);
});

it('assigns each subject to the classes that teach it, in the listed order', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed([AcademicClassSeeder::class, SubjectSeeder::class]);

    $subjectNamesOf = fn (string $className): array => ClassSubject::query()
        ->where('academic_class_id', AcademicClass::where('name', $className)->value('id'))
        ->with('subject')
        ->orderBy('order_index')
        ->get()
        ->pluck('subject.name')
        ->all();

    expect($subjectNamesOf('Class 3'))->toBe([
        'Bangla', 'English', 'Mathematics', 'General Science', 'Bangladesh and Global Studies', 'Religion and Moral Education',
    ])
        ->and($subjectNamesOf('Class 9 / Class 10'))->toContain('Physics', 'Bangla 1st Paper', 'Career Education')
        ->not->toContain('Physics 1st Paper')
        ->and($subjectNamesOf('Class 11 / Class 12'))->toContain('Physics 1st Paper', 'Physics 2nd Paper', 'Information and Communication Technology')
        ->not->toContain('Physics', 'Mathematics')
        ->and(AcademicClass::withCount('classSubjects')->get()->every(fn (AcademicClass $class): bool => $class->class_subjects_count > 0))->toBeTrue();
});

it('does not duplicate or reorder class assignments when run again', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed([AcademicClassSeeder::class, SubjectSeeder::class]);
    $assignments = ClassSubject::orderBy('id')->get(['academic_class_id', 'subject_id', 'order_index'])->toArray();

    $this->seed(SubjectSeeder::class);

    expect(ClassSubject::orderBy('id')->get(['academic_class_id', 'subject_id', 'order_index'])->toArray())->toBe($assignments);
});

it('still seeds the subjects when no class exists yet', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed(SubjectSeeder::class);

    expect(Subject::count())->toBe(67)
        ->and(ClassSubject::count())->toBe(0);
});
