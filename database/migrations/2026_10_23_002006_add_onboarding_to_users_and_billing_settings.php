<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dashboard's "Get started" checklist: when a user closed it for
     * good, and whether an Admin shows it at all.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_dismissed_at')->nullable();
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->boolean('onboarding_checklist_enabled')->default(true)->after('school_question_release_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_dismissed_at');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('onboarding_checklist_enabled');
        });
    }
};
