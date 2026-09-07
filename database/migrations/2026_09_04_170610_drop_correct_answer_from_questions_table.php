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
        // Superseded by `options[].is_correct` — matching the correct
        // answer by copying its text into a separate column broke down once
        // options could carry rich HTML/math (editor re-serialization drift)
        // and couldn't tell two identically-worded options apart. Keeping
        // the flag on the option itself also survives Repeater reordering
        // for free, which this column was originally added to protect
        // against (see git history).
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('correct_answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('correct_answer')->nullable();
        });
    }
};
