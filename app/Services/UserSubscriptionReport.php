<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The Admin's count of who signed in with Google — Students, Teachers,
 * Schools and Staff — and how many of them have ever subscribed.
 *
 * Every figure is read from one per-user summary of `subscriptions` (each
 * row once: bought, trial, gift or given by the Super Admin; the role's
 * free plan is never a row), grouped once over the table and joined to
 * the users — so the list is sorted by the database and never runs a
 * query per row.
 */
class UserSubscriptionReport
{
    /**
     * @param  UserRole|null  $role  One kind of user, or null for every Google role.
     */
    public function __construct(private readonly ?UserRole $role = null) {}

    /**
     * The roles that sign in with Google — everyone but the Admins.
     *
     * @return array<int, UserRole>
     */
    public static function googleRoles(): array
    {
        return [UserRole::Student, UserRole::Teacher, UserRole::School, UserRole::Staff];
    }

    /**
     * The users of the report, each with `subscriptions_count`,
     * `paid_count`, `gift_count`, `trial_count` and `last_subscribed_at`
     * added (0 / null for whoever never subscribed). Unsorted, for the
     * table to sort.
     *
     * @return Builder<User>
     */
    public function users(): Builder
    {
        return $this->ofRoles(User::query())
            ->leftJoinSub(self::perUser(), 'subscription_totals', 'subscription_totals.user_id', '=', 'users.id')
            ->select('users.*')
            ->selectRaw('coalesce(subscription_totals.subscriptions_count, 0) as subscriptions_count')
            ->selectRaw('coalesce(subscription_totals.paid_count, 0) as paid_count')
            ->selectRaw('coalesce(subscription_totals.gift_count, 0) as gift_count')
            ->selectRaw('coalesce(subscription_totals.trial_count, 0) as trial_count')
            ->addSelect('subscription_totals.last_subscribed_at');
    }

    /**
     * The table's "Subscription" filter: each choice, keyed by what
     * whereSubscriptionState() takes — the same groups as the cards.
     *
     * @return array<string, string>
     */
    public static function subscriptionStateOptions(): array
    {
        return [
            'subscribed' => __('Subscribed users'),
            'never' => __('Never subscribed'),
            'paid' => __('Bought a subscription'),
            'gifted' => __('Given a gift'),
            'trial' => __('Took a trial'),
            'on_trial' => __('Trial running'),
        ];
    }

    /**
     * Narrows users() to one subscriptionStateOptions() choice; an unknown
     * one leaves it as it is.
     *
     * @param  Builder<User>  $users  A query from users().
     * @return Builder<User>
     */
    public static function whereSubscriptionState(Builder $users, string $state): Builder
    {
        return match ($state) {
            'subscribed' => $users->whereNotNull('subscription_totals.user_id'),
            'never' => $users->whereNull('subscription_totals.user_id'),
            'paid' => $users->where('subscription_totals.paid_count', '>', 0),
            'gifted' => $users->where('subscription_totals.gift_count', '>', 0),
            'trial' => $users->where('subscription_totals.trial_count', '>', 0),
            'on_trial' => $users->where('subscription_totals.running_trial_count', '>', 0),
            default => $users,
        };
    }

    /**
     * How many users there are, per role and over all of them, in one
     * query: in total, ever subscribed and never, ever paid, ever given a
     * gift, and on a trial that is still running.
     *
     * Keyed by role value, plus `all`.
     *
     * @return array<string, array{total: int, subscribed: int, unsubscribed: int, paid: int, gifted: int, on_trial: int}>
     */
    public static function totals(): array
    {
        $byRole = (new self)->ofRoles(User::query())
            ->toBase()
            ->leftJoinSub(self::perUser(), 'subscription_totals', 'subscription_totals.user_id', '=', 'users.id')
            ->groupBy('users.role')
            ->select('users.role')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(subscription_totals.user_id) as subscribed')
            ->selectRaw('sum(case when subscription_totals.paid_count > 0 then 1 else 0 end) as paid')
            ->selectRaw('sum(case when subscription_totals.gift_count > 0 then 1 else 0 end) as gifted')
            ->selectRaw('sum(case when subscription_totals.running_trial_count > 0 then 1 else 0 end) as on_trial')
            ->get()
            ->keyBy('role');

        $empty = ['total' => 0, 'subscribed' => 0, 'unsubscribed' => 0, 'paid' => 0, 'gifted' => 0, 'on_trial' => 0];
        $totals = ['all' => $empty];

        foreach (self::googleRoles() as $role) {
            $row = $byRole->get($role->value);
            $figures = $empty;

            foreach (['total', 'subscribed', 'paid', 'gifted', 'on_trial'] as $key) {
                $figures[$key] = (int) ($row->{$key} ?? 0);
            }

            $figures['unsubscribed'] = $figures['total'] - $figures['subscribed'];
            $totals[$role->value] = $figures;

            foreach ($figures as $key => $count) {
                $totals['all'][$key] += $count;
            }
        }

        return $totals;
    }

    /**
     * One row per user who ever subscribed, grouped once over the table
     * (the `user_id` index): how many subscriptions, how many of them
     * paid / gifts / trials, how many trials still run, and the latest.
     * "Paid" is `Subscription::isPaid()` and "still running"
     * `Subscription::scopeActive()`, written as SQL.
     */
    private static function perUser(): QueryBuilder
    {
        return DB::table('subscriptions')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('subscriptions.user_id')
            ->select('subscriptions.user_id')
            ->selectRaw('count(*) as subscriptions_count')
            ->selectRaw('sum(case when '.Subscription::PAID_SQL.' then 1 else 0 end) as paid_count')
            ->selectRaw('sum(case when subscriptions.is_gift = 1 then 1 else 0 end) as gift_count')
            ->selectRaw('sum(case when subscription_plans.is_trial = 1 then 1 else 0 end) as trial_count')
            ->selectRaw(
                'sum(case when subscription_plans.is_trial = 1 and subscriptions.status = ? and (subscriptions.ends_at is null or subscriptions.ends_at > ?) then 1 else 0 end) as running_trial_count',
                [SubscriptionStatus::Active->value, now()],
            )
            ->selectRaw('max(subscriptions.created_at) as last_subscribed_at');
    }

    /**
     * @template TQuery of Builder
     *
     * @param  TQuery  $users
     * @return TQuery
     */
    private function ofRoles(Builder $users): Builder
    {
        return $this->role
            ? $users->where('users.role', $this->role)
            : $users->whereIn('users.role', self::googleRoles());
    }
}
