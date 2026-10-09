<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\BillingSetting;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\WalletService;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the Teacher, Student and Staff panels' "My Profile" page: the
 * phone number (the one way to actually reach a user who signs in with
 * Google and never reads email), the referral code they signed up with,
 * their own code to share, what referring has earned them, and — for the
 * roles that have one — their wallet. All the rules are ReferralService's
 * and WalletService's; this page only shows and collects, and what it
 * shows follows from the signed-in user's role, not from the panel.
 */
abstract class MyProfilePage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.my-profile';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['phone' => Auth::user()->phone]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')
                    ->label(__('Phone number'))
                    ->helperText($this->phoneHelperText())
                    ->tel()
                    ->placeholder('01XXXXXXXXX')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && User::normalizePhone($value) === null) {
                            $fail(__('Enter a valid mobile number, e.g. 01712345678.'));
                        }
                    }),

                TextInput::make('referral_code')
                    ->label(__('Referral code'))
                    ->helperText(__('Got a code from a friend or your teacher? Enter it before your first purchase.'))
                    ->visible(fn (): bool => $this->canEnterReferralCode())
                    ->maxLength(12),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();
        $phone = User::normalizePhone($data['phone'] ?? null);

        if ($phone !== $user->phone) {
            // A different number has not been verified by anyone yet.
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => null])->save();
        }

        if (filled($data['referral_code'] ?? null) && ! app(ReferralService::class)->attach($user, $data['referral_code'])) {
            $this->addError('data.referral_code', __('This referral code is not valid.'));

            return;
        }

        $this->form->fill(['phone' => $user->phone]);

        Notification::make()->title(__('Profile saved'))->success()->send();
    }

    public function canEnterReferralCode(): bool
    {
        return app(ReferralService::class)->canEnterCode(Auth::user());
    }

    /**
     * Whether this user has a code to share right now.
     */
    public function canRefer(): bool
    {
        $referrals = app(ReferralService::class);

        return $referrals->isEnabled() && $referrals->canRefer(Auth::user());
    }

    public function hasWallet(): bool
    {
        return Auth::user()->role->holdsWallet();
    }

    public function settings(): BillingSetting
    {
        return BillingSetting::current();
    }

    public function referrer(): ?User
    {
        return Auth::user()->referrer;
    }

    public function referralCode(): string
    {
        return app(ReferralService::class)->codeFor(Auth::user());
    }

    public function referralLink(): string
    {
        return app(ReferralService::class)->linkFor(Auth::user());
    }

    public function rewardPercent(): float
    {
        return app(ReferralService::class)->rewardPercentFor(Auth::user());
    }

    public function referredCount(): int
    {
        return Auth::user()->referrals()->count();
    }

    /**
     * @return array{maturing: float, matured: float, paid: float}
     */
    public function rewardSummary(): array
    {
        return once(fn (): array => app(ReferralService::class)->rewardSummaryFor(Auth::user()));
    }

    /**
     * @return Collection<int, ReferralReward>
     */
    public function rewards(): Collection
    {
        return Auth::user()->referralRewards()->latest('id')->limit(20)->get();
    }

    public function walletBalance(): float
    {
        return app(WalletService::class)->balanceOf($this->allWalletEntries());
    }

    /**
     * The balance and the list of movements are both read off the same
     * entries, fetched once per render.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    private function allWalletEntries(): SupportCollection
    {
        return once(fn (): SupportCollection => app(WalletService::class)->entriesFor(Auth::user()));
    }

    /**
     * The wallet's latest movements, newest first.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function walletEntries(): SupportCollection
    {
        return $this->allWalletEntries()->reverse()->take(20)->values();
    }

    private function phoneHelperText(): string
    {
        // The bonus is extra exams on a plan's monthly limit, so it only
        // means something to the roles that have a plan.
        $bonus = $this->hasWallet() ? BillingSetting::current()->phone_bonus_exams : 0;

        return $bonus > 0
            ? __('We use it for offers and important notices. Add it and get :count extra free exam(s) every month.', ['count' => $bonus])
            : __('We use it for offers and important notices.');
    }
}
