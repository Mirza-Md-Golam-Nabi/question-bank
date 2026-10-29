<?php

use App\Enums\DuplicateCheckStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The background duplicate check (QuestionDuplicateScreening): where each
 * question is in it, the two numbers that narrow a chapter down to the few
 * questions worth comparing (its guard and its size, read through one
 * index), and what it was found to look like. Every question already there
 * counts as clear until `questions:screen-duplicates` looks at it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('duplicate_status')->default(DuplicateCheckStatus::Clear->value)->index();
            $table->char('duplicate_guard', 40)->nullable();
            $table->unsignedInteger('duplicate_size')->nullable();

            $table->index(['chapter_id', 'question_type', 'duplicate_guard', 'duplicate_size'], 'questions_duplicate_lookup_index');
        });

        Schema::create('question_duplicate_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matched_question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->boolean('is_exact')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_duplicate_matches');

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_duplicate_lookup_index');
            $table->dropIndex(['duplicate_status']);
            $table->dropColumn(['duplicate_status', 'duplicate_guard', 'duplicate_size']);
        });
    }
};
