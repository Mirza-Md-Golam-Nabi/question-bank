<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a question or a past paper was approved — the day it joined the
 * question bank, which the dashboards' "Live database update" counts by
 * (QuestionBankGrowth). What is already approved takes its last approval
 * from the log, or else when it was written (an Admin's own); a paper,
 * its last change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('approved_by')->index();
        });

        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('approved_by')->index();
        });

        DB::table('questions')
            ->where('status', 'approved')
            ->update(['approved_at' => DB::raw(
                "coalesce((select max(question_approval_logs.created_at) from question_approval_logs where question_approval_logs.question_id = questions.id and question_approval_logs.action = 'approved'), questions.created_at)"
            )]);

        DB::table('board_question_papers')
            ->where('status', 'approved')
            ->update(['approved_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['approved_at']);
            $table->dropColumn('approved_at');
        });

        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->dropIndex(['approved_at']);
            $table->dropColumn('approved_at');
        });
    }
};
