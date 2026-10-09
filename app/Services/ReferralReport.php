<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The Admin's view of whether the referral programme pays for itself: for
 * each referrer, how many people they brought in, how many of those
 * bought, the cash that came in from them, and the rewards it cost —
 * still maturing, matured, or cancelled by a refund.
 *
 * Optionally narrowed to one kind of referrer and to a period. The period
 * applies to when each thing happened: when the person signed up, when
 * the payment was approved, when the reward was recorded.
 */
class ReferralReport
{
    public function __construct(
        private readonly ?UserRole $referrerRole = null,
        private readonly ?CarbonInterface $from = null,
        private readonly ?CarbonInterface $until = null,
    ) {}

    /**
     * One row per referrer, the one who brought in the most cash first.
     * Each row is the referrer's User with `referred_count`,
     * `buyers_count`, `cash_received`, `rewards_maturing`,
     * `rewards_matured` and `rewards_cancelled` added.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function rows(int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->whereHas('referrals')
            ->when($this->referrerRole, fn (Builder $query) => $query->where('role', $this->referrerRole))
            ->withCount([
                'referrals as referred_count' => fn (Builder $query) => $this->inPeriod($query, 'created_at'),
                'referrals as buyers_count' => fn (Builder $query) => $query
                    ->whereHas('payments', fn (Builder $payments) => $this->successful($payments)),
            ])
            ->addSelect(['cash_received' => $this->successful(Payment::query())
                ->selectRaw('coalesce(sum(payments.amount), 0)')
                ->join('users as referred', 'referred.id', '=', 'payments.user_id')
                ->whereColumn('referred.referred_by_id', 'users.id')])
            ->withSum(['referralRewards as rewards_maturing' => fn (Builder $query) => $this->inPeriod($query->maturing(), 'created_at')], 'amount')
            ->withSum(['referralRewards as rewards_matured' => fn (Builder $query) => $this->inPeriod($query->matured(), 'created_at')], 'amount')
            ->withSum(['referralRewards as rewards_cancelled' => fn (Builder $query) => $this->inPeriod($query->cancelled(), 'created_at')], 'amount')
            ->orderByDesc('cash_received')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * The same figures added up over every referrer the report covers.
     *
     * @return array{
     *     referred: int,
     *     buyers: int,
     *     cash_received: float,
     *     rewards_maturing: float,
     *     rewards_matured: float,
     *     rewards_cancelled: float,
     * }
     */
    public function totals(): array
    {
        $referred = fn (): Builder => User::query()->whereHas('referrer', fn (Builder $query) => $this->ofReferrerRole($query));
        $rewards = ReferralReward::totalsByState($this->inPeriod(
            ReferralReward::query()->whereHas('referrer', fn (Builder $query) => $this->ofReferrerRole($query)),
            'created_at',
        ));

        return [
            'referred' => $this->inPeriod($referred(), 'created_at')->count(),
            'buyers' => $referred()->whereHas('payments', fn (Builder $payments) => $this->successful($payments))->count(),
            'cash_received' => round((float) $this->successful(Payment::query())->whereIn('user_id', $referred()->select('id'))->sum('amount'), 2),
            'rewards_maturing' => $rewards['maturing'],
            'rewards_matured' => $rewards['matured'],
            'rewards_cancelled' => $rewards['cancelled'],
        ];
    }

    /**
     * Payments that count as money in: approved (and not since refunded),
     * inside the report's period.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $payments
     * @return TQuery
     */
    private function successful(Builder $payments): Builder
    {
        return $this->inPeriod($payments->where($payments->qualifyColumn('status'), PaymentStatus::Success), 'paid_at');
    }

    private function ofReferrerRole(Builder $referrers): Builder
    {
        return $referrers->when($this->referrerRole, fn (Builder $query) => $query->where('role', $this->referrerRole));
    }

    /**
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function inPeriod(Builder|Relation $query, string $column): Builder|Relation
    {
        $column = $query->qualifyColumn($column);

        return $query
            ->when($this->from, fn ($query) => $query->where($column, '>=', $this->from->copy()->startOfDay()))
            ->when($this->until, fn ($query) => $query->where($column, '<=', $this->until->copy()->endOfDay()));
    }
}
