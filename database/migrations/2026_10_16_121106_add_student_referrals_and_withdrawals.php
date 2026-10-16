<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referral, step 2: a student is under one referrer at a time, for a
 * limited number of days (`student_referrals`); a payment remembers who its
 * referrer was when it was placed; a reward remembers its percentage; and a
 * Teacher or Student can take their referral earnings out as cash
 * (`withdrawals`), which leaves their wallet as a ledger row. Wallet money
 * no longer expires, so its expiry setting goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            // code / link / exam — how the referrer was set.
            $table->string('source');
            // The teacher's exam whose share link set it.
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            // Until when the student may still swap the code they typed.
            $table->dateTime('changeable_until')->nullable();
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['student_id', 'ends_at']);
            $table->index(['referrer_id', 'ends_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('referrer_id')->nullable()->after('plan_id')->constrained('users')->nullOnDelete();
            $table->foreignId('student_referral_id')->nullable()->after('referrer_id')->constrained()->nullOnDelete();
        });

        // Payments so far: the referrer is who the buyer signed up with.
        DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->whereNotNull('users.referred_by_id')
            ->select('payments.id', 'users.referred_by_id')
            ->get()
            ->each(fn (object $payment) => DB::table('payments')->where('id', $payment->id)->update(['referrer_id' => $payment->referred_by_id]));

        Schema::table('referral_rewards', function (Blueprint $table) {
            $table->decimal('percent', 5, 2)->nullable()->after('amount');
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            // A Teacher or a Student.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('provider');
            // Encrypted (the `encrypted` cast), like a Staff member's bank details.
            $table->text('account_number');
            // The number has paid for a purchase this user was the referrer
            // of — a hint of self-referral for the Admin, fixed at request.
            $table->boolean('number_used_by_referred_buyer')->default(false);
            $table->string('status');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('withdrawal_id')->nullable()->after('payment_id')->constrained()->nullOnDelete();
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->renameColumn('referrer_reward_percent', 'student_reward_percent');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('teacher_reward_percent', 5, 2)->default(0)->after('student_reward_percent');
            $table->boolean('commission_first_purchase_only')->default(false)->after('staff_reward_percent');
            $table->unsignedSmallInteger('referral_lock_days')->default(90)->after('commission_first_purchase_only');
            $table->unsignedSmallInteger('referral_change_days')->default(1)->after('referral_lock_days');
            $table->decimal('teacher_reserve_amount', 10, 2)->default(0)->after('referral_change_days');
            $table->decimal('teacher_min_withdrawal', 10, 2)->default(0)->after('teacher_reserve_amount');
            $table->decimal('student_reserve_amount', 10, 2)->default(0)->after('teacher_min_withdrawal');
            $table->decimal('student_min_withdrawal', 10, 2)->default(0)->after('student_reserve_amount');
            $table->dropColumn('credit_expiry_months');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_expiry_months')->nullable();
            $table->dropColumn([
                'teacher_reward_percent', 'commission_first_purchase_only', 'referral_lock_days', 'referral_change_days',
                'teacher_reserve_amount', 'teacher_min_withdrawal', 'student_reserve_amount', 'student_min_withdrawal',
            ]);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->renameColumn('student_reward_percent', 'referrer_reward_percent');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('withdrawal_id');
        });

        Schema::dropIfExists('withdrawals');

        Schema::table('referral_rewards', function (Blueprint $table) {
            $table->dropColumn('percent');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_referral_id');
            $table->dropConstrainedForeignId('referrer_id');
        });

        Schema::dropIfExists('student_referrals');
    }
};
