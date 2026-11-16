<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The duplicate check of the approved questions (ApprovedDuplicateSweep):
 * every set of approved questions of a chapter found alike — waiting for
 * the Admin until resolved — and when the Admin last found a question to
 * be one of its own, after which it is never looked through again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_duplicate_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'chapter_id']);
        });

        Schema::create('approved_duplicate_set_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approved_duplicate_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->boolean('is_exact')->default(false);
            $table->timestamps();

            $table->unique(['approved_duplicate_set_id', 'question_id'], 'approved_duplicate_set_members_unique');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->timestamp('duplicate_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('duplicate_verified_at');
        });

        Schema::dropIfExists('approved_duplicate_set_members');
        Schema::dropIfExists('approved_duplicate_sets');
    }
};
