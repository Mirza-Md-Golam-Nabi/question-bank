<?php

namespace App\Services;

use App\Enums\WalletTransactionType;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The only place wallet credit is added, spent or counted. Credit never
 * leaves the system as cash: a Teacher or Student earns it through
 * referrals and spends it on their own subscription.
 *
 * A wallet is made of two things — the user's matured referral rewards
 * (ReferralReward) and the ledger of what they spent or had returned
 * (WalletTransaction). The balance is always worked out from those rows:
 * there is no stored balance to drift out of step, and no scheduled job
 * maturing or expiring credit — both are simply applied at the moment the
 * balance is read (the same way the monthly exam limit is counted).
 */
class WalletService
{
    /**
     * Everything that has gone into or out of the user's wallet, oldest
     * first: positive amounts are credit, negative ones spending.
     *
     * @return Collection<int, array{at: CarbonInterface, label: string, amount: float, expires_at: ?CarbonInterface}>
     */
    public function entriesFor(User $user): Collection
    {
        $transactions = $user->walletTransactions()->orderBy('id')->get()->map(fn (WalletTransaction $transaction): array => [
            'at' => $transaction->created_at,
            'label' => $transaction->type->getLabel(),
            'amount' => $transaction->amount,
            'expires_at' => $transaction->expires_at,
        ]);

        $rewards = $user->role->holdsWallet()
            ? $user->referralRewards()->matured()->get()->map(fn (ReferralReward $reward): array => [
                'at' => $reward->matures_at,
                'label' => __('Referral reward'),
                'amount' => $reward->amount,
                'expires_at' => $reward->expires_at,
            ])
            : collect();

        return $transactions->concat($rewards)->sortBy('at')->values();
    }

    /**
     * What the user can spend right now: credit is used oldest-first, and
     * credit past its expiry date is skipped.
     */
    public function balanceFor(User $user): float
    {
        return $this->balanceOf($this->entriesFor($user));
    }

    /**
     * The balance a wallet's entries (entriesFor()) add up to — for a
     * caller that already has them and would otherwise fetch them twice.
     *
     * @param  Collection<int, array{at: CarbonInterface, label: string, amount: float, expires_at: ?CarbonInterface}>  $entries
     */
    public function balanceOf(Collection $entries): float
    {
        /** @var array<int, array{left: float, expires_at: ?CarbonInterface}> $credits */
        $credits = [];

        foreach ($entries as $entry) {
            if ($entry['amount'] > 0) {
                $credits[] = ['left' => $entry['amount'], 'expires_at' => $entry['expires_at']];

                continue;
            }

            $owed = -$entry['amount'];

            foreach ($credits as &$credit) {
                if ($owed <= 0) {
                    break;
                }

                if ($this->hasExpired($credit['expires_at'], $entry['at'])) {
                    continue;
                }

                $taken = min($credit['left'], $owed);
                $credit['left'] -= $taken;
                $owed -= $taken;
            }
            unset($credit);
        }

        return round(collect($credits)
            ->reject(fn (array $credit): bool => $this->hasExpired($credit['expires_at'], now()))
            ->sum('left'), 2);
    }

    /**
     * When credit that becomes spendable at `$from` runs out: after
     * however many months the Admin has set, or never when that is blank.
     * Fixed on the row it is given to, so changing the setting later
     * doesn't touch credit already given.
     */
    public function expiryFor(CarbonInterface $from): ?CarbonInterface
    {
        $months = BillingSetting::current()->credit_expiry_months;

        return $months ? $from->copy()->addMonths($months) : null;
    }

    public function credit(User $user, float $amount, WalletTransactionType $type, ?Payment $payment = null): WalletTransaction
    {
        return $this->record($user, abs($amount), $type, $payment, $this->expiryFor(now()));
    }

    public function debit(User $user, float $amount, WalletTransactionType $type, ?Payment $payment = null): WalletTransaction
    {
        return $this->record($user, -abs($amount), $type, $payment);
    }

    private function record(User $user, float $amount, WalletTransactionType $type, ?Payment $payment, ?CarbonInterface $expiresAt = null): WalletTransaction
    {
        $transaction = new WalletTransaction([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'payment_id' => $payment?->id,
            'expires_at' => $expiresAt,
        ]);

        // Stamped by the application rather than the database, so it is on
        // the same clock the expiry dates are compared against.
        $transaction->forceFill(['created_at' => now()])->save();

        return $transaction;
    }

    private function hasExpired(?CarbonInterface $expiresAt, CarbonInterface $at): bool
    {
        return $expiresAt !== null && $expiresAt->lessThanOrEqualTo($at);
    }
}
