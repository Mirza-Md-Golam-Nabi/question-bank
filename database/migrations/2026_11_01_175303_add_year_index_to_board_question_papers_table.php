<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The past papers tables filter by year and list the years there are; the
 * ['status', 'year'] index only serves that once a status is chosen too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->dropIndex(['year']);
        });
    }
};
