<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Staff member is paid per past paper as well as per question: an
 * earning is for one or the other, and a paper earns once. What a paper
 * pays depends on its kind (board, medical, university…) — the Admin sets
 * one amount per kind (`paper_rates`, kind => amount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_earnings', function (Blueprint $table) {
            $table->foreignId('question_id')->nullable()->change();
            $table->foreignId('board_question_paper_id')->nullable()->after('question_id')->constrained()->cascadeOnDelete();
            $table->unique('board_question_paper_id');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->json('paper_rates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('paper_rates');
        });

        Schema::table('staff_earnings', function (Blueprint $table) {
            $table->dropUnique(['board_question_paper_id']);
            $table->dropConstrainedForeignId('board_question_paper_id');
        });
    }
};
