<?php

use App\Enums\EditorMode;
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
        Schema::table('questions', function (Blueprint $table) {
            // Which editor authored `question_text` — RichEditor (Tiptap JSON
            // dehydrated to HTML) and CKEditor (plain HTML with math widgets)
            // produce incompatible markup, so an edit needs to reopen the
            // same editor the content was originally written in.
            $table->string('editor_mode')->default(EditorMode::RichText->value)->after('question_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('editor_mode');
        });
    }
};
