<?php

namespace App\Filament\Support\Pages;

use App\Enums\MobileBankingProvider;
use App\Enums\UserRole;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\ShowsPlanSummary;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionMember;
use App\Models\SubscriptionPlan;
use App\Services\OtpService;
use App\Services\PlanConditions;
use App\Services\SubscriptionMemberService;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Shared by the Teacher and Student panels' "My Subscription" page — the
 * same "current plan + usage + buy a plan" view for both roles, just backed
 * by whichever user is logged into that panel. Prices, discounts, credit
 * and what happens to a payment are all SubscriptionPurchaseService's; this
 * page only collects the form and shows the result.
 */
abstract class MySubscriptionPage extends Page
{
    use NotifiesValidationFailures;
    use ShowsPlanSummary;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.my-subscription';

    /**
     * The plans on sale, kept for the rest of the request once read (not
     * between requests) — and forgotten after a purchase, which can take
     * the trial off the list.
     *
     * @var Collection<int, SubscriptionPlan>|null
     */
    private ?Collection $plansOnSale = null;

    /**
     * The buy form's subject options, keyed by the classes they are for.
     *
     * @var array<string, array<int, string>>
     */
    private array $pickableSubjects = [];

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function plans(): Collection
    {
        // Asked for by the price list, the plan cards and the buy action
        // in one render — fetched once for all of them.
        return $this->plansOnSale ??= $this->loadPlans();
    }

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    private function loadPlans(): Collection
    {
        $role = Auth::user()->role->subscriptionTargetRole();

        if (! $role) {
            return new Collection;
        }

        // The free trial only for someone who hasn't had it.
        return SubscriptionPlan::purchasableFor($role)->get()
            ->when(app(SubscriptionPurchaseService::class)->hasHadTrial(Auth::user()), fn (Collection $plans) => $plans->reject->is_trial->values());
    }

    /**
     * What each plan on sale costs this user, keyed by plan id — priced
     * together, so the user's discount is looked up once, not per plan.
     *
     * @return array<int, array{list_price: float, discount: float, wallet: float, payable: float}>
     */
    public function priceList(): array
    {
        return app(SubscriptionPurchaseService::class)->quotes(Auth::user(), $this->plans(), useWallet: false);
    }

    public function walletBalance(): float
    {
        return app(WalletService::class)->balanceFor(Auth::user());
    }

    public function pendingPayment(): ?Payment
    {
        return Auth::user()->pendingPayment()?->load('plan');
    }

    /**
     * @return Collection<int, Payment>
     */
    public function payments(): Collection
    {
        return Auth::user()->payments()->with('plan')->latest('id')->limit(10)->get();
    }

    /**
     * @return array{list_price: float, discount: float, wallet: float, payable: float}
     */
    public function quoteFor(SubscriptionPlan $plan, bool $useWallet = false): array
    {
        return app(SubscriptionPurchaseService::class)->quote(Auth::user(), $plan, $useWallet);
    }

    public function buyAction(): Action
    {
        return Action::make('buy')
            ->label(__('Buy'))
            ->modalHeading(fn (array $arguments): string => __('Buy :plan', ['plan' => $this->planFrom($arguments)->name]))
            ->modalSubmitActionLabel(fn (array $arguments): string => $this->planFrom($arguments)->is_trial ? __('Start free trial') : __('Submit'))
            ->fillForm(fn (array $arguments): array => [
                'use_wallet' => $this->walletBalance() > 0,
                'class_ids' => $this->defaultClassFor($this->planFrom($arguments)),
            ])
            ->schema(fn (array $arguments): array => $this->purchaseForm($this->planFrom($arguments)))
            ->action(function (array $data, array $arguments, Action $action): void {
                $payment = $this->rescueWithNotification(
                    fn () => app(SubscriptionPurchaseService::class)->request(Auth::user(), $this->planFrom($arguments), $data),
                    $action,
                );

                // What is on sale may have changed (a used trial leaves it).
                $this->plansOnSale = null;

                Notification::make()
                    ->title($payment->isSuccessful()
                        ? __('Your subscription is active.')
                        : __('Payment submitted. Your subscription starts as soon as it is approved.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The class the buy form starts on: the student's own class, when the
     * plan lets them pick it.
     */
    private function defaultClassFor(SubscriptionPlan $plan): mixed
    {
        $conditions = $plan->planConditions();
        $classId = Auth::user()->academic_class_id;

        if (! $conditions->needsClassPick() || ! $classId || ($conditions->classIds !== [] && ! in_array((int) $classId, $conditions->classIds, true))) {
            return null;
        }

        return $conditions->classPickCount === 1 ? $classId : [$classId];
    }

    /**
     * The subjects the buy form offers, as select options — what the plan
     * lets the buyer pick for the class(es) chosen so far (nothing before a
     * class is chosen, when the plan asks for one). Kept for the request,
     * as the field's help text asks again.
     *
     * @param  array<int, mixed>  $pickedClassIds
     * @return array<int, string>
     */
    private function pickableSubjectOptions(PlanConditions $conditions, array $pickedClassIds): array
    {
        $classIds = $conditions->classScope($pickedClassIds);

        if ($conditions->needsClassPick() && $classIds === []) {
            return [];
        }

        return $this->pickableSubjects[implode(',', $classIds)] ??= Subject::query()
            ->whereKey($conditions->pickableSubjectIds($classIds))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->display_name])
            ->all();
    }

    /**
     * Every plan on sale with its conditions in words, keyed by plan id.
     *
     * @return array<int, array<int, string>>
     */
    public function planDescriptions(): array
    {
        return $this->plans()->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->id => $plan->planConditions()->descriptions()])->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function planFrom(array $arguments): SubscriptionPlan
    {
        return $this->plans()->firstOrFail('id', (int) ($arguments['plan'] ?? 0));
    }

    /**
     * The fields shown depend on what is left to pay: nothing to send (and
     * so nothing to fill in) when wallet credit covers the whole price.
     *
     * @return array<int, mixed>
     */
    private function purchaseForm(SubscriptionPlan $plan): array
    {
        $settings = BillingSetting::current();
        $balance = $this->walletBalance();
        $hasToPay = fn (Get $get): bool => $this->quoteFor($plan, (bool) $get('use_wallet'))['payable'] > 0;
        $needsOtp = fn (Get $get): bool => (bool) $get('use_wallet')
            && $balance > 0
            && app(OtpService::class)->isRequiredForCredit();

        $conditions = $plan->planConditions();
        $pickOne = fn (?int $count): bool => $count === 1;

        return [
            // What the plan asks the buyer to pick: their class(es) and/or
            // subject(s). Only from what the plan lists, and only subjects
            // taught in every chosen (or the plan's) class.
            Select::make('class_ids')
                ->label(trans_choice('Class|Classes', $conditions->classPickCount ?? 1))
                ->options(fn (): array => AcademicClass::options($conditions->classIds ?: null))
                ->multiple(! $pickOne($conditions->classPickCount))
                ->maxItems($pickOne($conditions->classPickCount) ? null : $conditions->classPickCount)
                ->helperText(Auth::user()->role === UserRole::Student ? __('Your profile class changes to the one you choose.') : null)
                ->visible($conditions->needsClassPick())
                ->required($conditions->needsClassPick())
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('subject_ids', null)),

            Select::make('subject_ids')
                ->label(trans_choice('Subject|Subjects', $conditions->subjectPickCount ?? 1))
                ->options(fn (Get $get): array => $this->pickableSubjectOptions($conditions, (array) $get('class_ids')))
                ->helperText(fn (Get $get): ?string => match (true) {
                    $conditions->needsClassPick() && $conditions->classScope((array) $get('class_ids')) === [] => __('Choose the class first.'),
                    $this->pickableSubjectOptions($conditions, (array) $get('class_ids')) === [] => __('No subject is taught in every class of this plan.'),
                    count($conditions->classScope((array) $get('class_ids'))) > 1 => __('Only the subjects taught in every class of the plan.'),
                    default => null,
                })
                ->multiple(! $pickOne($conditions->subjectPickCount))
                ->maxItems($pickOne($conditions->subjectPickCount) ? null : $conditions->subjectPickCount)
                ->searchable()
                ->visible($conditions->needsSubjectPick())
                ->required($conditions->needsSubjectPick()),

            Toggle::make('use_wallet')
                ->label(__('Use my wallet credit (৳:amount)', ['amount' => number_format($balance, 2)]))
                ->visible($balance > 0)
                ->live(),

            TextEntry::make('summary')
                ->hiddenLabel()
                ->state(fn (Get $get): HtmlString => new HtmlString(view('filament.pages.partials.purchase-summary', [
                    'quote' => $this->quoteFor($plan, (bool) $get('use_wallet')),
                    'receivingNumbers' => $settings->receivingNumbers(),
                    'instructions' => $settings->payment_instructions,
                ])->render())),

            TextInput::make('otp_code')
                ->label(__('Verification code'))
                ->helperText(__('Spending wallet credit needs a code sent to your phone number.'))
                ->visible($needsOtp)
                ->required($needsOtp)
                ->suffixAction(
                    Action::make('sendOtp')
                        ->label(__('Send code'))
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->action(fn () => $this->sendOtp()),
                ),

            Radio::make('payer_provider')
                ->label(__('Sent with'))
                ->options(collect($settings->receivingNumbers())
                    ->map(fn (string $number, string $provider): string => MobileBankingProvider::from($provider)->getLabel())
                    ->all())
                ->inline()
                ->visible($hasToPay)
                ->required($hasToPay),

            TextInput::make('payer_number')
                ->label(__('Number you sent the payment from'))
                ->tel()
                ->visible($hasToPay)
                ->required($hasToPay),

            TextInput::make('transaction_id')
                ->label(__('Transaction ID'))
                ->maxLength(100)
                ->visible($hasToPay)
                ->required($hasToPay),
        ];
    }

    /**
     * The group plans this user bought and can share, each with its
     * members — what the "People on my plan" section lists.
     *
     * @return Collection<int, Subscription>
     */
    public function groupSubscriptions(): Collection
    {
        $members = app(SubscriptionMemberService::class);

        return Auth::user()->subscriptions()->active()->with('plan')->get()
            ->filter(fn (Subscription $subscription): bool => $members->canManage(Auth::user(), $subscription))
            ->each(fn (Subscription $subscription) => $subscription->setRelation('currentMembers', $members->membersOf($subscription)))
            ->values();
    }

    public function addMemberAction(): Action
    {
        return Action::make('addMember')
            ->label(__('Add someone'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->size('sm')
            ->modalHeading(__('Add someone to your plan'))
            ->modalDescription(__('They sign in with their own Google account; the plan shows up for them straight away.'))
            ->schema([
                TextInput::make('email')->label(__('Their Gmail address'))->email()->required(),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->rescueWithNotification(
                    fn () => app(SubscriptionMemberService::class)->add(Auth::user(), $this->ownSubscription($arguments), $data['email']),
                    $action,
                );

                Notification::make()->title(__('Added to your plan'))->success()->send();
            });
    }

    public function removeMemberAction(): Action
    {
        return Action::make('removeMember')
            ->label(__('Remove'))
            ->color('danger')
            ->size('xs')
            ->link()
            ->requiresConfirmation()
            ->modalDescription(fn (): string => __('You can take someone off the plan once every :days days.', ['days' => BillingSetting::current()->group_member_change_days]))
            ->action(function (array $arguments): void {
                $member = SubscriptionMember::query()
                    ->whereIn('subscription_id', Auth::user()->subscriptions()->select('id'))
                    ->findOrFail((int) ($arguments['member'] ?? 0));

                $removed = $this->rescueWithNotification(function () use ($member): bool {
                    app(SubscriptionMemberService::class)->remove(Auth::user(), $member);

                    return true;
                });

                if (! $removed) {
                    return;
                }

                Notification::make()->title(__('Removed from your plan'))->success()->send();
            });
    }

    /**
     * One of the user's own subscriptions, never someone else's.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function ownSubscription(array $arguments): Subscription
    {
        return Auth::user()->subscriptions()->findOrFail((int) ($arguments['subscription'] ?? 0));
    }

    public function sendOtp(): void
    {
        $sent = $this->rescueWithNotification(function (): bool {
            app(OtpService::class)->send(Auth::user());

            return true;
        });

        if (! $sent) {
            return;
        }

        Notification::make()->title(__('A verification code has been sent to your phone.'))->success()->send();
    }
}
