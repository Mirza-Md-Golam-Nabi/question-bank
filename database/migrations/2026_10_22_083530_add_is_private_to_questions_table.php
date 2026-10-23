<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A School's own questions (CLAUDE.md rule 12): only that School sees
     * them, they never reach the shared pool or an Admin's review.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_latest');
            $table->index(['created_by', 'is_private', 'is_latest']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['created_by', 'is_private', 'is_latest']);
            $table->dropColumn('is_private');
        });
    }
};
