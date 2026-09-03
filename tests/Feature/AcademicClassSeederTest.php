<?php

use App\Models\AcademicClass;
use Database\Seeders\AcademicClassSeeder;

it('does not seed academic classes outside the local environment', function () {
    $this->seed(AcademicClassSeeder::class);

    expect(AcademicClass::count())->toBe(0);
});
