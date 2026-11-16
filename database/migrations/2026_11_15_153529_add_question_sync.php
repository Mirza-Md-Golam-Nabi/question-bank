<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bringing questions from the live server to the test server
 * (App\Services\QuestionSyncImporter): on the live server, until when the
 * sharing API is open; on the test server, what each question, stimulus
 * and past paper came from there — so a second fetch knows it — and every
 * fetch with what it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->timestamp('question_sync_open_until')->nullable();
            $table->unsignedSmallInteger('question_sync_open_hours')->default(2);
        });

        Schema::table('questions', function (Blueprint $table) {
            // The first version's id on the live server — the same for every version there.
            $table->unsignedBigInteger('remote_origin_id')->nullable()->index();
            $table->unsignedInteger('remote_version')->nullable();
        });

        Schema::table('question_stimuli', function (Blueprint $table) {
            $table->unsignedBigInteger('remote_id')->nullable()->index();
        });

        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->unsignedBigInteger('remote_id')->nullable()->index();
        });

        Schema::create('question_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->unsignedBigInteger('remote_id');
            $table->string('remote_label');
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('board_question_paper_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->json('counts')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_sync_runs');

        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->dropIndex(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('question_stimuli', function (Blueprint $table) {
            $table->dropIndex(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['remote_origin_id']);
            $table->dropColumn(['remote_origin_id', 'remote_version']);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['question_sync_open_until', 'question_sync_open_hours']);
        });
    }
};
