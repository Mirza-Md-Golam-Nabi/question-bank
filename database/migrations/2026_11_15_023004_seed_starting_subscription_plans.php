<?php

use App\Models\AcademicClass;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Puts the starting price list on the live site once, on deploy
 * (`migrate --force`), without needing SSH. The seeder never overwrites
 * or doubles a plan, so a site that already has plans keeps them.
 *
 * A database with no classes yet (a fresh install, the test database) is
 * left alone — `db:seed` adds the plans there after the classes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! AcademicClass::query()->exists()) {
            return;
        }

        (new SubscriptionPlanSeeder)->run();
    }

    /**
     * The plans are left in place: by now they may have been bought or
     * changed by the Admin.
     */
    public function down(): void {}
};
