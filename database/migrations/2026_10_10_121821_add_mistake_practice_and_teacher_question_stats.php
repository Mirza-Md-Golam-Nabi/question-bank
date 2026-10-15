<?php

use App\Models\ExamAttempt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A student's "mistakes list": the questions they last got wrong,
        // each with how many times in a row they have since got it right.
        Schema::create('student_question_mistakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // The first version of the question, so a later correction of
            // the question is still the same mistake.
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('correct_streak')->default(0);
            // The teacher's exam it was got wrong on, while that exam's
            // answers are still hidden: until they are released, practising
            // this mistake would show its answer.
            $table->foreignId('locked_by_exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'question_id']);
        });

        // How a teacher's own students do on each question, across all of
        // that teacher's exams.
        Schema::create('teacher_question_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            // The first version of the question, as above.
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('answer_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->timestamps();

            $table->unique(['teacher_id', 'question_id']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            // Set once the attempt has gone into the two tables above, so
            // it never goes in twice.
            $table->timestamp('statistics_recorded_at')->nullable()->after('difficulty_counted_at');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('mistake_clear_streak')->default(2)->after('difficulty_medium_max_percent');
        });

        // The exams already sat count from day one.
        ExamAttempt::recordUnrecordedStatistics();
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('mistake_clear_streak');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('statistics_recorded_at');
        });

        Schema::dropIfExists('teacher_question_stats');
        Schema::dropIfExists('student_question_mistakes');
    }
};
