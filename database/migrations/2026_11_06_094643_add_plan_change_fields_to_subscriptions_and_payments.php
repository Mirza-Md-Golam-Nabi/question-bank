<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Changing a running subscription to another plan.
 *
 * `subscriptions.plan_price` is what the plan it holds is counted as worth
 * when it is changed: the plan's list price when it was bought, 0 for a
 * gift. A payment can now be a plan change (`type`) rather than a purchase:
 * it records the plan before (`previous_plan_id`), the money paid for an
 * upgrade (`amount`) or owed back for a downgrade (`refund_amount`), and
 * how the Admin gave that back (`refund_method`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('plan_price', 10, 2)->default(0)->after('selections');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('type', 20)->default('purchase')->after('user_id');
            $table->foreignId('previous_plan_id')->nullable()->after('plan_id')->constrained('subscription_plans')->nullOnDelete();
            $table->decimal('refund_amount', 10, 2)->nullable()->after('amount');
            $table->string('refund_method', 20)->nullable()->after('refund_amount');
        });

        // What existing subscriptions were bought for: the list price of
        // the payment that started them — nothing for a gift.
        DB::table('subscriptions')
            ->where('is_gift', false)
            ->update(['plan_price' => DB::raw('coalesce((select max(payments.list_price) from payments where payments.subscription_id = subscriptions.id), 0)')]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_plan_id');
            $table->dropColumn(['type', 'refund_amount', 'refund_method']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('plan_price');
        });
    }
};
