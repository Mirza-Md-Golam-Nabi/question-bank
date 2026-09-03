<?php

use App\Models\Subject;
use Database\Seeders\SubjectSeeder;

it('does not seed subjects outside the local environment', function () {
    $this->seed(SubjectSeeder::class);

    expect(Subject::count())->toBe(0);
});
