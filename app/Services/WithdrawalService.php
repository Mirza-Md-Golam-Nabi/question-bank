<?php

namespace App\Services;

use App\Enums\MobileBankingProvider;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A Teacher or Student taking their referral earnings out as cash — the
 * only code that creates a withdrawal or moves it from one state to
 * another:
 *
 *   request  → the user asks for an amount to be sent to their bKash/
 *              Nagad/Rocket number; it leaves their wallet at once
 *   markPaid → the Admin has sent it (recorded with the transaction id)
 *   reject   → it won't be sent; the amount goes back to the wallet
 *
 * The Admin's reserve for the user's role (BillingSetting::reserveFor())
 * always stays in the wallet: it can only be spent on their own next
 * subscription, never taken out. Every step is one transaction that locks
 * what it changes and re-checks its state first, so a double click does
 * nothing the second time, and every step is logged — never with the
 * account number.
 */
class WithdrawalService
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Who can take money out: the roles with a wallet (Teacher, Student).
     * A Staff member's referral rewards are paid with their payout instead.
     */
    public function canWithdraw(User $user): bool
    {
        return $user->role->holdsWallet();
    }

    /**
     * Where a user's wallet stands for taking money out: the balance, the
     * part of it that has to stay, what can be taken out now, the smallest
     * amount that can be asked for, and whether a request is waiting.
     *
     * @return array{balance: float, reserve: float, withdrawable: float, minimum: float, pending: ?Withdrawal}
     */
    public function summaryFor(User $user): array
    {
        $settings = BillingSetting::current();
        $balance = max($this->wallet->balanceFor($user), 0.0);
        $reserve = $settings->reserveFor($user->role);

        return [
            'balance' => $balance,
            'reserve' => $reserve,
            'withdrawable' => round(max($balance - $reserve, 0), 2),
            'minimum' => $settings->minimumWithdrawalFor($user->role),
            'pending' => $this->pendingFor($user),
        ];
    }

    public function pendingFor(User $user): ?Withdrawal
    {
        return $user->withdrawals()->where('status', WithdrawalStatus::Requested)->latest('id')->first();
    }

    /**
     * @throws ValidationException
     */
    public function request(User $user, float $amount, MobileBankingProvider|string|null $provider, ?string $accountNumber): Withdrawal
    {
        if (! $this->canWithdraw($user)) {
            throw ValidationException::withMessages(['amount' => __('Only a teacher or a student can take money out.')]);
        }

        $provider = $provider instanceof MobileBankingProvider ? $provider : MobileBankingProvider::tryFrom((string) $provider);
        $accountNumber = User::normalizePhone($accountNumber);

        if (! $provider) {
            throw ValidationException::withMessages(['provider' => __('Choose where the money should be sent.')]);
        }

        if ($accountNumber === null) {
            throw ValidationException::withMessages(['account_number' => __('Enter a valid mobile number, e.g. 01712345678.')]);
        }

        return DB::transaction(function () use ($user, $amount, $provider, $accountNumber): Withdrawal {
            // Serialises this user's requests: the balance and the pending
            // check below are read under the lock.
            User::whereKey($user->id)->lockForUpdate()->value('id');

            $summary = $this->summaryFor($user);
            $amount = round($amount, 2);

            if ($summary['pending']) {
                throw ValidationException::withMessages(['amount' => __('You already have a withdrawal waiting to be paid.')]);
            }

            if ($amount <= 0 || $amount < $summary['minimum']) {
                throw ValidationException::withMessages(['amount' => __('The smallest amount you can take out is ৳:amount.', ['amount' => number_format($summary['minimum'], 2)])]);
            }

            if ($amount > $summary['withdrawable']) {
                throw ValidationException::withMessages(['amount' => __('You can take out up to ৳:amount now.', ['amount' => number_format($summary['withdrawable'], 2)])]);
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'provider' => $provider,
                'account_number' => $accountNumber,
                // What self-referral through a second account looks like:
                // the money goes to a number that paid for a purchase this
                // user was rewarded for. A hint for the Admin, not a block.
                'number_used_by_referred_buyer' => Payment::query()
                    ->where('referrer_id', $user->id)
                    ->where('payer_number', $accountNumber)
                    ->exists(),
                'status' => WithdrawalStatus::Requested,
            ]);

            $this->wallet->debit($user, $amount, WalletTransactionType::Withdrawal, withdrawal: $withdrawal);

            Log::info('Withdrawal requested.', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'provider' => $provider->value,
                'number_used_by_referred_buyer' => $withdrawal->number_used_by_referred_buyer,
            ]);

            return $withdrawal;
        });
    }

    /**
     * The Admin has sent the money. False when the request was no longer
     * waiting — already paid or rejected.
     */
    public function markPaid(Withdrawal $withdrawal, User $admin, string $transactionId): bool
    {
        return $this->closeRequest($withdrawal, $admin, WithdrawalStatus::Paid, ['transaction_id' => trim($transactionId)]);
    }

    /**
     * The money won't be sent: the request is closed and the amount goes
     * back into the user's wallet. False when it was no longer waiting.
     */
    public function reject(Withdrawal $withdrawal, User $admin, ?string $note = null): bool
    {
        return $this->closeRequest($withdrawal, $admin, WithdrawalStatus::Rejected, ['note' => $note], function (Withdrawal $withdrawal): void {
            $this->wallet->credit($withdrawal->user, $withdrawal->amount, WalletTransactionType::WithdrawalReturn, withdrawal: $withdrawal);
        });
    }

    /**
     * What paying and rejecting share: the request locked and re-checked
     * (so a double click does nothing the second time), closed with who
     * handled it and when, anything else that has to happen, and a log line.
     *
     * @param  array<string, mixed>  $details
     * @param  (Closure(Withdrawal): void)|null  $then
     */
    private function closeRequest(Withdrawal $withdrawal, User $admin, WithdrawalStatus $status, array $details, ?Closure $then = null): bool
    {
        return DB::transaction(function () use ($withdrawal, $admin, $status, $details, $then): bool {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();

            if (! $withdrawal?->isRequested()) {
                return false;
            }

            $withdrawal->update([
                'status' => $status,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                ...$details,
            ]);

            $then?->__invoke($withdrawal);

            Log::info('Withdrawal closed.', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
                'amount' => $withdrawal->amount,
                'status' => $status->value,
                'handled_by' => $admin->id,
            ]);

            return true;
        });
    }
}
