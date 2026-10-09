<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            // One reward per payment, ever.
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->dateTime('matures_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained('staff_payouts')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['referrer_id', 'matures_at']);
        });

        // Rewards used to be written straight into the wallet ledger. Move
        // any that exist into the new table — already matured, since their
        // credit was spendable from the start — and drop the ledger rows.
        $reversedPaymentIds = DB::table('wallet_transactions')->where('type', 'reward_reversal')->pluck('payment_id');

        DB::table('wallet_transactions')
            ->where('type', 'referral_reward')
            ->whereNotNull('payment_id')
            ->orderBy('id')
            ->get()
            ->unique('payment_id')
            ->each(fn (object $reward) => DB::table('referral_rewards')->insert([
                'referrer_id' => $reward->user_id,
                'payment_id' => $reward->payment_id,
                'amount' => $reward->amount,
                'matures_at' => $reward->created_at,
                'expires_at' => $reward->expires_at,
                'cancelled_at' => $reversedPaymentIds->contains($reward->payment_id) ? now() : null,
                'created_at' => $reward->created_at,
            ]));

        DB::table('wallet_transactions')->whereIn('type', ['referral_reward', 'reward_reversal'])->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
