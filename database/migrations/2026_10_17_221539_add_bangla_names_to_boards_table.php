<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A paper source (board, medical college, university…) is named in both
 * languages, in full and short: `name`/`short_name` stay the English ones,
 * the Bangla ones are added. A question shows the short name of where it
 * came from ("ঢাবি ২০২৩" / "DU 2023").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->string('name_bn')->nullable()->after('name');
            $table->string('short_name_bn')->nullable()->after('short_name');
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn(['name_bn', 'short_name_bn']);
        });
    }
};
