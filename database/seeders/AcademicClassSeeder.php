<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use Illuminate\Database\Seeder;

/**
 * Local-only convenience data — only runs when APP_ENV=local, so it never
 * touches a staging/production database even if called directly.
 */
class AcademicClassSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $names = [
            'Class 3',
            'Class 4',
            'Class 5',
            'Class 6',
            'Class 7',
            'Class 8',
            'Class 9 / Class 10',
            'Class 11 / Class 12',
        ];

        foreach ($names as $index => $name) {
            AcademicClass::firstOrCreate(
                ['name' => $name],
                ['order_index' => $index + 1],
            );
        }
    }
}
