<?php

use App\Services\StudentMistakeTracker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student's mistakes list takes every exam they sit, past papers too: a
 * mistake is a question-bank question or a past paper's MCQ, and it keeps
 * what the student last answered, on which exam, when, and how often they
 * have got it wrong. A practice exam made of mistakes can hold a past
 * paper's MCQs beside the question bank's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_question_mistakes', function (Blueprint $table) {
            $table->foreignId('question_id')->nullable()->change();
            $table->foreignId('board_mcq_question_id')->nullable()->after('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('wrong_count')->default(1)->after('correct_streak');
            // What the student picked the last time they got it wrong — null
            // when they left it blank.
            $table->text('last_answer')->nullable()->after('wrong_count');
            $table->foreignId('last_wrong_exam_id')->nullable()->after('last_answer')->constrained('exams')->nullOnDelete();
            $table->timestamp('last_wrong_at')->nullable()->after('last_wrong_exam_id');

            $table->unique(['student_id', 'board_mcq_question_id']);
            $table->index(['student_id', 'last_wrong_at']);
        });

        // The past paper MCQs on a practice exam of mistakes.
        Schema::create('exam_board_mcq_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_mcq_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'board_mcq_question_id']);
        });

        // The list is built again from every paper handed in, oldest first,
        // by the same path a paper handed in today takes — which brings in
        // the past papers already sat and what was answered each time.
        DB::table('student_question_mistakes')->delete();
        app(StudentMistakeTracker::class)->rebuildFromSubmittedAttempts();
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_board_mcq_questions');

        DB::table('student_question_mistakes')->whereNull('question_id')->delete();

        Schema::table('student_question_mistakes', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'last_wrong_at']);
            $table->dropUnique(['student_id', 'board_mcq_question_id']);
            $table->dropConstrainedForeignId('last_wrong_exam_id');
            $table->dropConstrainedForeignId('board_mcq_question_id');
            $table->dropColumn(['wrong_count', 'last_answer', 'last_wrong_at']);
            $table->foreignId('question_id')->nullable(false)->change();
        });
    }
};
