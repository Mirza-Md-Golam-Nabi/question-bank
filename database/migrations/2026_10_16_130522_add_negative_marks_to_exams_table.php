<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negative marking on a teacher's online exam: the marks taken off for each
 * wrong MCQ answer (none = no negative marking). Blank answers lose nothing.
 * The amount the teacher's form starts on is the Admin's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->decimal('negative_marks', 5, 2)->nullable()->after('total_marks');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('default_negative_marks', 5, 2)->default(0.25)->after('mistake_clear_streak');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('default_negative_marks');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('negative_marks');
        });
    }
};
