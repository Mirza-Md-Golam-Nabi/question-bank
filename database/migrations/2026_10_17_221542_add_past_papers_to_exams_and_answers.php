<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's exam or a student's practice can be a whole past paper: the
 * exam points at the paper, and an answer to one of its MCQs points at that
 * MCQ — the paper's questions never enter the question pool (CLAUDE.md
 * rule 5). An admission paper has no subject, so neither has its exam.
 *
 * A paper an exam was made from can't be deleted from under it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->change();
            $table->foreignId('board_question_paper_id')->nullable()->after('class_subject_id')->constrained()->restrictOnDelete();
            $table->index(['created_by', 'board_question_paper_id', 'created_at']);
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->foreignId('question_id')->nullable()->change();
            $table->foreignId('board_mcq_question_id')->nullable()->after('question_id')->constrained()->cascadeOnDelete();
            $table->unique(['attempt_id', 'board_mcq_question_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropUnique(['attempt_id', 'board_mcq_question_id']);
            $table->dropConstrainedForeignId('board_mcq_question_id');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['created_by', 'board_question_paper_id', 'created_at']);
            $table->dropConstrainedForeignId('board_question_paper_id');
        });
    }
};
