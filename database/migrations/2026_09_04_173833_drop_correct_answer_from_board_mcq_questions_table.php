<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Same fix as the regular `questions` table: `options[].is_correct`
        // replaces a separate content-matched `correct_answer` column.
        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->dropColumn('correct_answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_mcq_questions', function (Blueprint $table) {
            $table->string('correct_answer')->nullable();
        });
    }
};
