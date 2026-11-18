<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // An OMR check (exam_type 'omr_check'): no questions, only the
            // answer key the teacher typed in — how many options, the marks
            // of each question and each one's correct option (Exam::answerKey()).
            $table->json('answer_key')->nullable()->after('omr_questions');
            // Which number on a scanned OMR sheet tells the students apart:
            // 'roll' or 'registration' (App\Enums\OmrIdentity).
            $table->string('omr_identity', 20)->default('roll')->after('answer_key');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            // A scanned OMR sheet's registration number, when the exam goes by it.
            $table->string('registration_no', 30)->nullable()->after('school_roll');
            $table->index(['exam_id', 'registration_no']);
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            // An answer to an OMR check's question, by its number on the sheet.
            $table->unsignedSmallInteger('answer_key_number')->nullable()->after('board_mcq_question_id');
            $table->unique(['attempt_id', 'answer_key_number']);
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropUnique(['attempt_id', 'answer_key_number']);
            $table->dropColumn('answer_key_number');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'registration_no']);
            $table->dropColumn('registration_no');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['answer_key', 'omr_identity']);
        });
    }
};
