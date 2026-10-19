<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A question-bank MCQ can carry an optional explanation of why its answer
 * is right, as a past paper's MCQ already does. Shown only where the
 * correct answer is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->longText('explanation')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('explanation');
        });
    }
};
