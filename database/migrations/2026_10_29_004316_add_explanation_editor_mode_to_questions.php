<?php

use App\Enums\EditorMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An MCQ's explanation is written in its own editor, picked apart from the
 * question text's, so each question remembers which one it opens in. Every
 * explanation written before this was written in CKEditor.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['questions', 'board_mcq_questions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('explanation_editor_mode')->default(EditorMode::RichText->value)->after('explanation');
            });

            DB::table($tableName)->whereNotNull('explanation')->update(['explanation_editor_mode' => EditorMode::CkEditor->value]);
        }
    }

    public function down(): void
    {
        foreach (['questions', 'board_mcq_questions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('explanation_editor_mode');
            });
        }
    }
};
