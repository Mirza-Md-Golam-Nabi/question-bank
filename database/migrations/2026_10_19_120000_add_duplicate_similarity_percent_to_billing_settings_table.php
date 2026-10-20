<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How alike two questions of a chapter (or of a past paper) must be before
 * one is called a possible duplicate of the other — the question and its
 * options each, in percent (DuplicateQuestionFinder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('duplicate_similarity_percent')->default(85)->after('question_import_max');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('duplicate_similarity_percent');
        });
    }
};
