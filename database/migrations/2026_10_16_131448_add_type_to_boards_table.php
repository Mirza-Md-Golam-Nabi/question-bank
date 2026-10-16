<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "board" becomes any source of past papers: an education board, a
 * medical or university admission test, a job exam, or a school/college's
 * test exam. Papers stay a subsystem of their own, never mixed with the
 * question pool (CLAUDE.md rule 5). Existing rows are education boards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->string('type')->default('board')->after('short_name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
