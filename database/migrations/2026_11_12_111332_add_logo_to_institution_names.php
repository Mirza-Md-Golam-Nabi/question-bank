<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An institution name's logo (public disk), printed beside the name on a
 * question paper — only while the name itself can be printed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_names', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('institution_names', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
