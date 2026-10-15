<?php

use App\Services\QuestionDifficultyTracker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // How many students have answered the question on a teacher's
            // exam and how many got it right — what its difficulty is
            // worked out from. Kept on the question itself, so the answers
            // they were counted from can be deleted later.
            $table->unsignedInteger('answer_count')->default(0)->after('difficulty');
            $table->unsignedInteger('correct_answer_count')->default(0)->after('answer_count');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            // Set once the attempt has been added to those counts, so it is
            // never added twice.
            $table->timestamp('difficulty_counted_at')->nullable()->after('submitted_at');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedInteger('difficulty_min_answers')->default(30)->after('teacher_question_import_enabled');
            $table->decimal('difficulty_hard_max_percent', 5, 2)->default(33)->after('difficulty_min_answers');
            $table->decimal('difficulty_medium_max_percent', 5, 2)->default(66)->after('difficulty_hard_max_percent');
        });

        // The exams already sat count from day one.
        app(QuestionDifficultyTracker::class)->countUncountedAttempts();
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['difficulty_min_answers', 'difficulty_hard_max_percent', 'difficulty_medium_max_percent']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('difficulty_counted_at');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['answer_count', 'correct_answer_count']);
        });
    }
};
