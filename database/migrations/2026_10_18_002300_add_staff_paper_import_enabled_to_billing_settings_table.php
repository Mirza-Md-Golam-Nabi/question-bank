<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether Staff may add a past paper's questions from pasted JSON. Off
 * until the Admin switches it on for all of them; an Admin always can.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->boolean('staff_paper_import_enabled')->default(false)->after('teacher_question_import_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('staff_paper_import_enabled');
        });
    }
};
