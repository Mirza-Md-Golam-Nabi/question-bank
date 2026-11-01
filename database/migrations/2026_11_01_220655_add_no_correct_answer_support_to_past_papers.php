<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->boolean('has_correct_answer')->default(true)->after('options');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->string('no_answer_marking')->default('excluded')->after('negative_marks');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('no_answer_marking');
        });

        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->dropColumn('has_correct_answer');
        });
    }
};
