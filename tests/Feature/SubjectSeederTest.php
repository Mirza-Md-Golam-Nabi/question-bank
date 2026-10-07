<?php

use App\Models\Subject;
use Database\Seeders\SubjectSeeder;

it('does not seed subjects outside the local environment', function () {
    $this->seed(SubjectSeeder::class);

    expect(Subject::count())->toBe(0);
});

it('seeds every subject with an english name, a bangla name and a unique short name', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $this->seed(SubjectSeeder::class);

    $subjects = Subject::all();

    expect($subjects)->toHaveCount(56)
        ->and($subjects->pluck('short_name')->unique())->toHaveCount(56)
        ->and($subjects->pluck('name_bn')->unique())->toHaveCount(56)
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
