<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('staff_reward_percent', 5, 2)->default(0)->after('referrer_reward_percent');
            $table->unsignedSmallInteger('refund_window_days')->default(0)->after('credit_expiry_months');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['staff_reward_percent', 'refund_window_days']);
        });
    }
};
