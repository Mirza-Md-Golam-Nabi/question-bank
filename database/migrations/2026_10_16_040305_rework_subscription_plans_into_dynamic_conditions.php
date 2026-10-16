<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A plan stops being "a price + a monthly limit" and becomes a price, a
 * length and a list of conditions the Admin adds and removes (classes,
 * subjects, difficulties, features, daily/monthly limits). A subscription
 * keeps a copy of the conditions it was bought with and the classes/
 * subjects the buyer picked, so a later edit of the plan never changes what
 * someone already paid for. An exam remembers which subscription it was
 * counted against (none = the free plan), and a student has a class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('duration_unit')->default('unlimited')->after('price');
            $table->unsignedSmallInteger('duration_length')->nullable()->after('duration_unit');
            $table->date('ends_on')->nullable()->after('duration_length');
            $table->json('conditions')->nullable()->after('ends_on');
            $table->boolean('is_trial')->default(false)->after('is_default_free');
            $table->boolean('is_active')->default(true)->after('is_trial');
        });

        // The old billing cycle and monthly limit, said the new way.
        foreach (DB::table('subscription_plans')->get() as $plan) {
            [$unit, $length] = match ($plan->billing_cycle) {
                'monthly' => ['months', 1],
                'yearly' => ['years', 1],
                default => ['unlimited', null],
            };

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'duration_unit' => $unit,
                'duration_length' => $length,
                'conditions' => json_encode($plan->monthly_exam_limit === null ? [] : [
                    ['type' => 'monthly_exam_limit', 'data' => ['limit' => (int) $plan->monthly_exam_limit]],
                ]),
            ]);
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'monthly_exam_limit']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('plan_id');
            $table->json('selections')->nullable()->after('conditions');
        });

        // Existing subscriptions keep what their plan says today.
        foreach (DB::table('subscriptions')->get(['id', 'plan_id']) as $subscription) {
            DB::table('subscriptions')->where('id', $subscription->id)->update([
                'conditions' => DB::table('subscription_plans')->where('id', $subscription->plan_id)->value('conditions') ?? '[]',
            ]);
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->json('selections')->nullable()->after('plan_id');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('academic_class_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_class_id');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('selections');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'selections']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('billing_cycle')->default('free');
            $table->unsignedInteger('monthly_exam_limit')->nullable();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['duration_unit', 'duration_length', 'ends_on', 'conditions', 'is_trial', 'is_active']);
        });
    }
};
