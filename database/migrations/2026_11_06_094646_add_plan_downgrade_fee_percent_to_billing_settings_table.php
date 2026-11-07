<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much of the price difference is kept when a subscription is changed
 * to a cheaper plan — the rest is given back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('plan_downgrade_fee_percent', 5, 2)->default(10)->after('refund_window_days');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('plan_downgrade_fee_percent');
        });
    }
};
