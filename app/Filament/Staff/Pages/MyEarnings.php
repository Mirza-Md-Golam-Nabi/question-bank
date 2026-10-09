<?php

namespace App\Filament\Staff\Pages;

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentMethod;
use App\Enums\QuestionStatus;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\Question;
use App\Models\StaffEarning;
use App\Models\StaffProfile;
use App\Services\ReferralService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

class MyEarnings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use TranslatesPageLabels;

    protected string $view = 'filament.staff.pages.my-earnings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $profile = $this->profile();

        $this->form->fill([
            // The Radio's own ->default(PaymentMethod::MobileBanking) never
            // actually applies here: Filament's fill() only runs
            // component-level defaults when it's called with a *fully*
            // null state, and mount() always passes a real (non-null)
            // array — so the fallback has to be resolved explicitly here
            // instead, and it must match the Radio's default or "new staff
            // member sees Mobile Banking selected" silently breaks again.
            'payment_method' => $profile?->payment_method ?? PaymentMethod::MobileBanking,
            'bank_account_number' => $profile?->bank_account_number,
            'bank_name' => $profile?->bank_name,
            'branch_name' => $profile?->branch_name,
            'account_holder_name' => $profile?->account_holder_name,
            'mobile_banking_number' => $profile?->mobile_banking_number,
            'mobile_banking_provider' => $profile?->mobile_banking_provider ?? MobileBankingProvider::Bkash,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('payment_method')
                    ->label(__('Payment method'))
                    ->options(PaymentMethod::class)
                    ->live()
                    ->inline()
                    ->inlineLabel(false)
                    ->required(),

                Group::make()
                    ->visible(fn (Get $get) => $get('payment_method') === PaymentMethod::MobileBanking)
                    ->schema([
                        Radio::make('mobile_banking_provider')
                            ->label(__('Provider'))
                            ->options(MobileBankingProvider::class)
                            ->inline()
                            ->inlineLabel(false)
                            ->required(fn (Get $get) => $get('payment_method') === PaymentMethod::MobileBanking),
                        TextInput::make('mobile_banking_number')
                            ->label(__('Mobile number'))
                            ->tel()
                            ->required(fn (Get $get) => $get('payment_method') === PaymentMethod::MobileBanking),
                    ]),

                Group::make()
                    ->visible(fn (Get $get) => $get('payment_method') === PaymentMethod::Bank)
                    ->schema([
                        TextInput::make('account_holder_name')
                            ->required(fn (Get $get) => $get('payment_method') === PaymentMethod::Bank),
                        TextInput::make('bank_name'),
                        TextInput::make('branch_name'),
                        TextInput::make('bank_account_number'),
                    ]),
            ])
            ->statePath('data');
    }

    public function saveBankInfo(): void
    {
        $data = $this->form->getState();

        // ->visible() hides the other group's fields without dropping them
        // from form state, so switching payment_method without clearing
        // here would leave stale bKash/bank data sitting in the row
        // alongside whichever method is actually selected.
        if ($data['payment_method'] === PaymentMethod::MobileBanking) {
            $data['account_holder_name'] = null;
            $data['bank_name'] = null;
            $data['branch_name'] = null;
            $data['bank_account_number'] = null;
        } else {
            $data['mobile_banking_provider'] = null;
            $data['mobile_banking_number'] = null;
        }

        StaffProfile::updateOrCreate(['user_id' => Auth::id()], $data);

        Notification::make()->title(__('Bank info saved'))->success()->send();
    }

    public function profile(): ?StaffProfile
    {
        return StaffProfile::where('user_id', Auth::id())->first();
    }

    public function pendingQuestionsCount(): int
    {
        return Question::where('created_by', Auth::id())
            ->where('status', QuestionStatus::Pending)
            ->where('is_latest', true)
            ->count();
    }

    /**
     * The page's three totals, added up by the database in one query and
     * read once per render (never by loading the earning rows themselves).
     *
     * Computed straight from `staff_earnings` rather than the StaffProfile
     * counters — those counters are a nice-to-have summary, but they only
     * get bumped when a StaffProfile row already exists (i.e. after the
     * staff member has saved bank info at least once), so a staff member's
     * very first approved question would otherwise show as ৳0 here even
     * though the earning itself was recorded correctly.
     *
     * @return array{count: int, earned: float, paid: float, unpaid: float, this_month: float}
     */
    private function totals(): array
    {
        return once(fn (): array => StaffEarning::totalsFor(Auth::id()));
    }

    public function totalQuestionsApproved(): int
    {
        return $this->totals()['count'];
    }

    public function totalEarned(): float
    {
        return $this->totals()['earned'];
    }

    public function totalPaid(): float
    {
        return $this->totals()['paid'];
    }

    /**
     * What referring customers has earned this staff member — kept apart
     * from the per-question earnings above, and paid in the same payout
     * once matured.
     *
     * @return array{maturing: float, matured: float, paid: float}
     */
    public function referralRewardSummary(): array
    {
        return app(ReferralService::class)->rewardSummaryFor(Auth::user());
    }

    /**
     * Grouped by class_subject (not just subject name) — the same subject
     * can belong to more than one class, each with its own chapter set
     * (CLAUDE.md: `class_subjects` pivot, not a class-independent subject),
     * so grouping by subject name alone would silently merge a staff
     * member's Class 9 Physics and Class 10 Physics earnings into one row.
     */
    public function subjectBreakdown(): SupportCollection
    {
        return StaffEarning::byClassSubjectFor(Auth::id());
    }
}
