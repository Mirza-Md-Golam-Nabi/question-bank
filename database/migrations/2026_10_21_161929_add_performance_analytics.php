<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('performance_min_questions')->default(5)->after('default_negative_marks');
            $table->unsignedSmallInteger('performance_trend_exams')->default(10)->after('performance_min_questions');
            $table->unsignedTinyInteger('performance_falling_exams')->default(3)->after('performance_trend_exams');
            $table->unsignedSmallInteger('performance_inactive_days')->default(14)->after('performance_falling_exams');
        });

        // A student's analytics read their handed-in papers newest first.
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index(['student_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'submitted_at']);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['performance_min_questions', 'performance_trend_exams', 'performance_falling_exams', 'performance_inactive_days']);
        });
    }
};
