<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('practice_minutes_per_question', 4, 2)->default(1)->after('mistake_clear_streak');
            $table->unsignedSmallInteger('practice_minutes_step')->default(5)->after('practice_minutes_per_question');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['practice_minutes_per_question', 'practice_minutes_step']);
        });
    }
};
