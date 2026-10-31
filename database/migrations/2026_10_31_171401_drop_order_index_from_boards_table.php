<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paper sources are always listed by name, so they no longer have a display
 * order. The create migration no longer adds the column either; this removes
 * it from a database that ran the older version of that migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('boards', 'order_index')) {
            Schema::table('boards', function (Blueprint $table) {
                $table->dropColumn('order_index');
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore: the create migration no longer has this column.
    }
};
