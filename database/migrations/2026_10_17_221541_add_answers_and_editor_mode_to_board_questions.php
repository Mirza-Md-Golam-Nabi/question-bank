<?php

use App\Enums\EditorMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A past paper's questions are written with the same editors as the
 * question bank (so each remembers which one), and can carry an
 * explanation: an MCQ why its answer is right, each CQ sub-question its
 * answer. Both are optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->string('editor_mode')->default(EditorMode::RichText->value)->after('question_text');
            $table->longText('explanation')->nullable()->after('options');
        });

        Schema::table('board_cq_questions', function (Blueprint $table) {
            $table->string('editor_mode')->default(EditorMode::RichText->value)->after('question_text');
        });

        Schema::table('board_cq_question_parts', function (Blueprint $table) {
            $table->longText('answer')->nullable()->after('part_image');
        });
    }

    public function down(): void
    {
        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->dropColumn(['editor_mode', 'explanation']);
        });

        Schema::table('board_cq_questions', function (Blueprint $table) {
            $table->dropColumn('editor_mode');
        });

        Schema::table('board_cq_question_parts', function (Blueprint $table) {
            $table->dropColumn('answer');
        });
    }
};
