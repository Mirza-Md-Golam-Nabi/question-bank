<?php

namespace App\Filament\Support\Pages;

use App\Enums\MobileBankingProvider;
use App\Enums\UserRole;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ReferralReward;
use App\Models\StudentReferral;
use App\Models\Withdrawal;
use App\Services\ReferralService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the Teacher and Student panels' "Referrals & commission" page:
 * the students under the user's referral and how long each has left, what
 * the commission adds up to, and taking it out as cash. Every rule is
 * ReferralService's and WithdrawalService's; this page only shows them and
 * collects the request. A panel's page only says where its plans are.
 */
abstract class ReferralsAndCommissionPage extends Page implements HasTable
{
    use InteractsWithTable;
    use NotifiesValidationFailures;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.referrals-and-commission';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $title = 'Referrals & commission';

    /**
     * The panel's "My Subscription" page, where a paid plan is bought.
     */
    abstract public function subscriptionUrl(): string;

    /**
     * The students under this user's referral right now, the one with the
     * least time left first.
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('My students'))
            ->description(__('Students under your referral now. Every purchase they make in this time earns you commission.'))
            ->query(StudentReferral::query()->where('referrer_id', Auth::id())->active()->with('student'))
            ->defaultSort('ends_at')
            ->columns([
                TextColumn::make('student.name')->label(__('Student'))
                    // Never a referred student's full email or phone.
                    ->description(fn (StudentReferral $record): string => $record->student->maskedEmail()),
                TextColumn::make('source')->label(__('How'))->badge(),
                TextColumn::make('starts_at')->label(__('Since'))->date('M j, Y'),
                TextColumn::make('ends_at')->label(__('Days left'))
                    ->state(fn (StudentReferral $record): int => $record->daysLeft())
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('ends_at', $direction)),
            ])
            ->emptyStateHeading(__('No students under your referral yet'))
            ->emptyStateDescription(fn (): string => $this->isTeacher()
                ? __('A student who opens your exam link while logged in, or enters your code, comes under your referral.')
                : __('A student who enters your code comes under your referral.'));
    }

    /**
     * Read once per request, and again after a withdrawal is asked for.
     *
     * @var array{balance: float, reserve: float, withdrawable: float, minimum: float, pending: ?Withdrawal}|null
     */
    private ?array $withdrawalSummary = null;

    /**
     * @return array{balance: float, reserve: float, withdrawable: float, minimum: float, pending: ?Withdrawal}
     */
    public function summary(): array
    {
        return $this->withdrawalSummary ??= app(WithdrawalService::class)->summaryFor(Auth::user());
    }

    /**
     * @return array{maturing: float, matured: float, paid: float}
     */
    public function rewardSummary(): array
    {
        return once(fn (): array => app(ReferralService::class)->rewardSummaryFor(Auth::user()));
    }

    public function earnsCommission(): bool
    {
        return app(ReferralService::class)->earnsCommission(Auth::user());
    }

    public function commissionPercent(): float
    {
        return app(ReferralService::class)->rewardPercentFor(Auth::user());
    }

    public function referralCode(): string
    {
        return app(ReferralService::class)->codeFor(Auth::user());
    }

    public function isTeacher(): bool
    {
        return Auth::user()->role === UserRole::Teacher;
    }

    /**
     * The latest commissions, each with the (masked) student who paid.
     *
     * @return Collection<int, ReferralReward>
     */
    public function rewards(): Collection
    {
        return Auth::user()->recentReferralRewards();
    }

    /**
     * @return Collection<int, Withdrawal>
     */
    public function withdrawals(): Collection
    {
        return Auth::user()->withdrawals()->latest('id')->limit(20)->get();
    }

    /**
     * Everything that went into or out of the wallet, newest first.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function walletEntries(): SupportCollection
    {
        return app(WalletService::class)->entriesFor(Auth::user())->reverse()->take(20)->values();
    }

    public function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label(__('Take out cash'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->disabled(fn (): bool => $this->summary()['pending'] !== null
                || $this->summary()['withdrawable'] <= 0
                || $this->summary()['withdrawable'] < $this->summary()['minimum'])
            ->modalDescription(fn (): string => __('You can take out up to ৳:amount now. ৳:reserve always stays in your wallet for your next subscription.', [
                'amount' => number_format($this->summary()['withdrawable'], 2),
                'reserve' => number_format($this->summary()['reserve'], 2),
            ]))
            ->fillForm(fn (): array => [
                'amount' => $this->summary()['withdrawable'],
                'provider' => MobileBankingProvider::Bkash,
                // The number used last time, to save typing it again.
                'account_number' => Auth::user()->withdrawals()->latest('id')->first()?->account_number ?? Auth::user()->phone,
            ])
            ->schema([
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->prefix('৳')
                    ->numeric()
                    ->minValue(fn (): float => max($this->summary()['minimum'], 1))
                    ->maxValue(fn (): float => $this->summary()['withdrawable'])
                    ->required(),
                Radio::make('provider')
                    ->label(__('Send to'))
                    ->options(MobileBankingProvider::class)
                    ->inline()
                    ->required(),
                TextInput::make('account_number')
                    ->label(__('Number'))
                    ->tel()
                    ->placeholder('01XXXXXXXXX')
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $this->rescueWithNotification(
                    fn () => app(WithdrawalService::class)->request(Auth::user(), (float) $data['amount'], $data['provider'], $data['account_number']),
                    $action,
                );

                $this->withdrawalSummary = null;

                Notification::make()->title(__('Request sent. The money is sent to your number once the Admin pays it.'))->success()->send();
            });
    }
}
