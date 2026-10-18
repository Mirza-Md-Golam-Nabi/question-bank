<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admission test's paper is a source + year (+ an optional unit, such as
 * a university's "Ka" unit) with every subject together — so the class +
 * subject an education board's paper has becomes optional. A paper also
 * gets how it is sat: its time and what a wrong MCQ answer costs.
 *
 * The unique key gains the unit. An admission paper's class + subject is
 * null, which a unique index lets repeat, so BoardQuestionPaper checks
 * those itself. The new index is created before the old
 * one is dropped, so the board_id foreign key always has an index to use
 * (MySQL refuses to drop the one a foreign key relies on otherwise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->foreignId('class_subject_id')->nullable()->change();
            // '' rather than null for "no unit", so a board's paper (whose
            // class + subject is never null) stays unique in the database.
            $table->string('unit', 50)->default('')->after('year');
            $table->unsignedInteger('duration_minutes')->nullable()->after('unit');
            $table->decimal('negative_marks', 5, 2)->nullable()->after('duration_minutes');
            $table->unique(['board_id', 'class_subject_id', 'year', 'unit'], 'board_papers_source_year_unit_unique');
        });

        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->dropUnique(['board_id', 'class_subject_id', 'year']);
            $table->index(['status', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->unique(['board_id', 'class_subject_id', 'year']);
            $table->dropIndex(['status', 'year']);
        });

        Schema::table('board_question_papers', function (Blueprint $table) {
            $table->dropUnique('board_papers_source_year_unit_unique');
            $table->dropColumn(['unit', 'duration_minutes', 'negative_marks']);
        });
    }
};
