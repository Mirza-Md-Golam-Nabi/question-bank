<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\InstitutionName;
use App\Models\Payment;
use App\Models\SchoolProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The school/coaching names a Teacher or School prints at the top of a
 * question paper, and the only code that adds, changes or pays for one:
 *
 *  - the first name is free and always usable; a School's starts as the
 *    name on its profile;
 *  - every other name is paid for, the Admin's price for the Admin's days
 *    (`BillingSetting.institution_name_price` / `institution_name_days`),
 *    the same for everyone, with no refund, discount or commission. Paying
 *    again adds the days to the end of what is left. Once the days run out
 *    only the free name can be printed, until it is paid for again;
 *  - a name can be changed once every `institution_name_change_days` —
 *    otherwise the free one could stand in for any number of names.
 *
 * Paying goes through the same bKash/Nagad-and-Admin route as a
 * subscription (SubscriptionPurchaseService::placeManual()); the Admin's
 * approval lands in approve() here.
 */
class InstitutionNameService
{
    public const MAX_LENGTH = 150;

    public function __construct(
        private readonly SubscriptionPurchaseService $purchases,
        private readonly WalletService $wallet,
        private readonly PurchaseNotifications $notifications,
    ) {}

    /**
     * Whether this user prints question papers and so has names at all.
     */
    public function hasNames(User $user): bool
    {
        return $user->role->setsExams();
    }

    /**
     * All of the user's names, the free one first. A School that has none
     * yet starts with the name on its profile.
     *
     * @return Collection<int, InstitutionName>
     */
    public function namesFor(User $user): Collection
    {
        $names = $user->institutionNames()->inOrder()->get();

        if ($names->isEmpty() && $user->role === UserRole::School && filled($schoolName = SchoolProfile::for($user)->name)) {
            $names->push($user->institutionNames()->create(['name' => $schoolName, 'is_free' => true]));
        }

        return $names;
    }

    /**
     * The names that can be printed now.
     *
     * @return Collection<int, InstitutionName>
     */
    public function usableNamesFor(User $user): Collection
    {
        return $this->namesFor($user)->filter->isUsable()->values();
    }

    /**
     * Whether extra names can be bought at all: only once the Admin has
     * set a price.
     */
    public function isOnSale(): bool
    {
        return BillingSetting::current()->institution_name_price > 0;
    }

    /**
     * What one more period of an extra name costs this user right now.
     *
     * @return array{list_price: float, discount: float, wallet: float, payable: float}
     */
    public function quote(User $user, bool $useWallet): array
    {
        $price = (float) BillingSetting::current()->institution_name_price;
        $balance = $useWallet && $user->role->holdsWallet() ? max($this->wallet->balanceFor($user), 0) : 0.0;
        $wallet = round(min($balance, $price), 2);

        return [
            'list_price' => $price,
            'discount' => 0.0,
            'wallet' => $wallet,
            'payable' => round($price - $wallet, 2),
        ];
    }

    /**
     * Saves the free name: the first time it is made, later it is changed
     * (once every so many days).
     *
     * @throws ValidationException
     */
    public function saveFreeName(User $user, string $name): InstitutionName
    {
        $this->ensureHasNames($user);

        $free = $this->namesFor($user)->firstWhere('is_free', true);

        if ($free) {
            return $this->rename($user, $free, $name);
        }

        $name = $this->cleanName($user, $name);

        return $user->institutionNames()->create(['name' => $name, 'is_free' => true]);
    }

    /**
     * When the name can be changed next; null when it can be now.
     */
    public function nextRenameAt(InstitutionName $institutionName): ?CarbonInterface
    {
        $next = $institutionName->renamed_at?->copy()->addDays(BillingSetting::current()->institution_name_change_days);

        return $next?->isFuture() ? $next : null;
    }

    /**
     * @throws ValidationException
     */
    public function rename(User $user, InstitutionName $institutionName, string $name): InstitutionName
    {
        $this->ensureOwns($user, $institutionName);

        $name = $this->cleanName($user, $name, $institutionName);

        if ($name === $institutionName->name) {
            return $institutionName;
        }

        if ($next = $this->nextRenameAt($institutionName)) {
            throw ValidationException::withMessages(['name' => __('This name can be changed again on :date.', [
                'date' => $next->translatedFormat('j F Y'),
            ])]);
        }

        $old = $institutionName->name;
        $institutionName->update(['name' => $name, 'renamed_at' => now()]);

        Log::info('Institution name changed.', ['institution_name_id' => $institutionName->id, 'user_id' => $user->id, 'from' => $old, 'to' => $name]);

        return $institutionName;
    }

    /**
     * Asks for one more name: the name is kept straight away (not usable
     * yet) and its payment waits for the Admin — unless wallet credit
     * covered it all, when it is usable at once.
     *
     * @param  array{use_wallet?: bool, otp_code?: ?string, payer_provider?: mixed, payer_number?: ?string, transaction_id?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function requestNew(User $user, string $name, array $details): Payment
    {
        $this->ensureHasNames($user);

        if (! $this->namesFor($user)->contains('is_free', true)) {
            throw ValidationException::withMessages(['name' => __('Save your free name first.')]);
        }

        $name = $this->cleanName($user, $name);

        return $this->placePayment($user, $details, fn (): InstitutionName => $user->institutionNames()->create(['name' => $name]));
    }

    /**
     * Pays for more days of an extra name — added to the end of the days
     * it still has.
     *
     * @param  array{use_wallet?: bool, otp_code?: ?string, payer_provider?: mixed, payer_number?: ?string, transaction_id?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function requestRenewal(User $user, InstitutionName $institutionName, array $details): Payment
    {
        $this->ensureOwns($user, $institutionName);

        if ($institutionName->is_free) {
            throw ValidationException::withMessages(['name' => __('The first name is always free.')]);
        }

        return $this->placePayment($user, $details, fn (): InstitutionName => $institutionName);
    }

    /**
     * Removes an extra name that is not paid for now and has no payment
     * waiting — one asked for and turned down, or whose days are over.
     *
     * @throws ValidationException
     */
    public function delete(User $user, InstitutionName $institutionName): void
    {
        $this->ensureOwns($user, $institutionName);

        if (! $this->canDelete($institutionName)) {
            throw ValidationException::withMessages(['name' => __('Only an extra name that is not paid for now can be removed.')]);
        }

        $institutionName->delete();

        Log::info('Institution name removed.', ['institution_name_id' => $institutionName->id, 'user_id' => $user->id]);
    }

    public function canDelete(InstitutionName $institutionName): bool
    {
        return ! $institutionName->is_free
            && ! $institutionName->isUsable()
            && ! $institutionName->payments()->where('status', PaymentStatus::Pending)->exists();
    }

    /**
     * The Admin has checked the money: the name gets the payment's days,
     * after whatever it still had. False when the payment was no longer
     * pending.
     */
    public function approve(Payment $payment, ?User $reviewer = null): bool
    {
        $approved = DB::transaction(function () use ($payment, $reviewer): bool {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment?->isPending() || ! $payment->isForInstitutionName() || ! $payment->institutionName) {
                return false;
            }

            $institutionName = InstitutionName::whereKey($payment->institution_name_id)->lockForUpdate()->first();
            $from = $institutionName->paid_until?->isFuture() ? $institutionName->paid_until : now();

            $institutionName->update(['paid_until' => $from->copy()->addDays((int) $payment->period_days)]);

            $payment->update([
                'status' => PaymentStatus::Success,
                'paid_at' => now(),
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            Log::info('Payment approved.', [
                'payment_id' => $payment->id,
                'institution_name_id' => $institutionName->id,
                'paid_until' => $institutionName->paid_until->toDateTimeString(),
                'reviewed_by' => $reviewer?->id,
            ]);

            return true;
        });

        if ($approved) {
            $this->notifications->institutionNameApproved($payment->refresh());
        }

        return $approved;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  Closure(): InstitutionName  $institutionName  The name it pays for, made or found inside the payment's transaction.
     *
     * @throws ValidationException
     */
    private function placePayment(User $user, array $details, Closure $institutionName): Payment
    {
        if (! $this->isOnSale()) {
            throw ValidationException::withMessages(['name' => __('Extra names are not on sale right now.')]);
        }

        $settings = BillingSetting::current();

        $payment = $this->purchases->placeManual(
            $user,
            fn (bool $useWallet): array => $this->quote($user, $useWallet),
            $details,
            fn (): array => [
                'type' => PaymentType::InstitutionName,
                'institution_name_id' => $institutionName()->id,
                'period_days' => $settings->institution_name_days,
            ],
        );

        if ($payment->amount <= 0) {
            $this->approve($payment);
        }

        $payment->refresh();
        $this->notifications->institutionNamePlaced($payment);

        return $payment;
    }

    /**
     * The name trimmed, with its spaces collapsed — required, not too
     * long, and not one the user already has.
     *
     * @throws ValidationException
     */
    private function cleanName(User $user, string $name, ?InstitutionName $except = null): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('Write the name.')]);
        }

        if (mb_strlen($name) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['name' => __('The name can be at most :count characters.', ['count' => self::MAX_LENGTH])]);
        }

        $taken = $user->institutionNames()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->pluck('name')
            ->contains(fn (string $existing): bool => mb_strtolower($existing) === mb_strtolower($name));

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('You already have this name.')]);
        }

        return $name;
    }

    /**
     * @throws ValidationException
     */
    private function ensureHasNames(User $user): void
    {
        if (! $this->hasNames($user)) {
            throw ValidationException::withMessages(['name' => __('Only a Teacher or a School prints institution names.')]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureOwns(User $user, InstitutionName $institutionName): void
    {
        if ($institutionName->user_id !== $user->id) {
            throw ValidationException::withMessages(['name' => __('This name is not yours.')]);
        }
    }
}
