<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // How many questions the printed OMR sheet has rows for, as the
            // teacher chose (App\Support\OmrSheet) — never fewer than the
            // paper's MCQ; null = just the paper's MCQ.
            $table->unsignedSmallInteger('omr_questions')->nullable()->after('mcq_marks');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('omr_questions');
        });
    }
};
