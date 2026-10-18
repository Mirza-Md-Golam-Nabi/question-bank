<?php

use App\Enums\EditorMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared-stimulus MCQs ("read the stimulus and answer questions 42 and
 * 43"): one stimulus — text and/or an image — with two or three MCQs under
 * it. Each MCQ stays a whole MCQ of its own (graded, counted and versioned
 * as before); it only knows its stimulus and its place under it. The same
 * shape in the question bank and on a past paper (two tables, never mixed —
 * CLAUDE.md rule 5), with the same column names on both question tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->longText('text');
            $table->string('editor_mode')->default(EditorMode::RichText->value);
            $table->string('image')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('stimulus_id')->nullable()->after('topic_id')->constrained('question_stimuli')->nullOnDelete();
            $table->unsignedTinyInteger('stimulus_order')->nullable()->after('stimulus_id');
        });

        Schema::create('board_mcq_stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_question_paper_id')->constrained()->cascadeOnDelete();
            $table->longText('text');
            $table->string('editor_mode')->default(EditorMode::RichText->value);
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::table('board_mcq_questions', function (Blueprint $table) {
            // Removing a paper's stimulus removes the questions under it.
            $table->foreignId('stimulus_id')->nullable()->after('board_question_paper_id')->constrained('board_mcq_stimuli')->cascadeOnDelete();
            $table->unsignedTinyInteger('stimulus_order')->nullable()->after('stimulus_id');
        });
    }

    public function down(): void
    {
        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stimulus_id');
            $table->dropColumn('stimulus_order');
        });

        Schema::dropIfExists('board_mcq_stimuli');

        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stimulus_id');
            $table->dropColumn('stimulus_order');
        });

        Schema::dropIfExists('question_stimuli');
    }
};
