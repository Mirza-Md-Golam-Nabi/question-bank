<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            // On by default: teachers already had the feature before it
            // became something the Admin can switch off.
            $table->boolean('teacher_question_import_enabled')->default(true)->after('question_import_max');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('teacher_question_import_enabled');
        });
    }
};
