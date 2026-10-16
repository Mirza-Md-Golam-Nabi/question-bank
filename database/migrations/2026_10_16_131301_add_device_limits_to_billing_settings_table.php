<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many devices a Teacher or Student may be signed in on at once
 * (0 = no limit) — a check against one account being shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('teacher_device_limit')->default(3)->after('group_member_change_days');
            $table->unsignedSmallInteger('student_device_limit')->default(2)->after('teacher_device_limit');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['teacher_device_limit', 'student_device_limit']);
        });
    }
};
