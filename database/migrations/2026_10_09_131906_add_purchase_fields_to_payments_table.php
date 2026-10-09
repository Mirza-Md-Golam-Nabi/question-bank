<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('subscription_id')->constrained('subscription_plans')->nullOnDelete();
            // `amount` stays what was paid in cash; these record how the
            // plan's price was brought down to it.
            $table->decimal('list_price', 8, 2)->nullable()->after('plan_id');
            $table->decimal('discount_amount', 8, 2)->default(0)->after('list_price');
            $table->decimal('wallet_amount', 8, 2)->default(0)->after('discount_amount');
            $table->string('payer_provider')->nullable()->after('gateway_transaction_id');
            $table->string('payer_number', 20)->nullable()->after('payer_provider');
            $table->foreignId('reviewed_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->dateTime('refunded_at')->nullable()->after('reviewed_at');
            $table->string('review_note')->nullable()->after('refunded_at');

            $table->index('payer_number');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['payer_number']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn([
                'list_price', 'discount_amount', 'wallet_amount', 'payer_provider',
                'payer_number', 'reviewed_at', 'refunded_at', 'review_note',
            ]);
        });
    }
};
