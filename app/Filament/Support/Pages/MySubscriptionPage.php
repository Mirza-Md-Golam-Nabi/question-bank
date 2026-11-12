<?php

namespace App\Filament\Support\Pages;

use App\Enums\UserRole;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\SendsCreditOtp;
use App\Filament\Support\Concerns\ShowsPlanSummary;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ManualPaymentFields;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\PlanPickFields;
use App\Filament\Support\SubscriptionChangeActions;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionMember;
use App\Models\SubscriptionPlan;
use App\Services\PlanConditions;
use App\Services\SubscriptionChangeService;
use App\Services\SubscriptionMemberService;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

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
    use SendsCreditOtp;
    use ShowsPlanSummary;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.my-subscription';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    protected static ?int $navigationSort = 2;

    /**
     * The plans on sale, kept for the rest of the request once read (not
     * between requests) — and forgotten after a purchase, which can take
     * the trial off the list.
     *
     * @var Collection<int, SubscriptionPlan>|null
     */
    private ?Collection $plansOnSale = null;

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
        return Auth::user()->pendingPayment()?->load(['plan', 'previousPlan', 'institutionName']);
    }

    /**
     * @return Collection<int, Payment>
     */
    public function payments(): Collection
    {
        return Auth::user()->payments()->with(['plan', 'previousPlan', 'institutionName'])->latest('id')->limit(10)->get();
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
        $conditions = $plan->planConditions();

        return [
            // What the plan asks the buyer to pick: their class(es) and/or
            // subject(s).
            ...(new PlanPickFields)->components(
                fn (): PlanConditions => $conditions,
                Auth::user()->role === UserRole::Student ? __('Your profile class changes to the one you choose.') : null,
            ),

            ...ManualPaymentFields::components(
                fn (bool $useWallet): array => $this->quoteFor($plan, $useWallet),
                $this->walletBalance(),
                fn () => $this->sendOtp(),
                __('Plan price'),
            ),
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

    /**
     * The user's own subscriptions they can change, each with what can be
     * changed: the plan, and (while unused) the classes/subjects. Asked
     * here because a button rendered with arguments doesn't check its own
     * visibility.
     *
     * @return SupportCollection<int, array{subscription: Subscription, plan: bool, selections: bool}>
     */
    public function changeableSubscriptions(): SupportCollection
    {
        $changes = app(SubscriptionChangeService::class);

        return Auth::user()->subscriptions()->active()->with('plan')->latest('starts_at')->get()
            ->map(fn (Subscription $subscription): array => [
                'subscription' => $subscription,
                'plan' => $changes->canChangePlan(Auth::user(), $subscription),
                'selections' => $changes->canChangeSelections(Auth::user(), $subscription),
            ])
            ->filter(fn (array $changeable): bool => $changeable['plan'] || $changeable['selections'])
            ->values()
            ->toBase();
    }

    public function changePlanAction(): Action
    {
        return SubscriptionChangeActions::changePlan()->size('sm');
    }

    public function changeSelectionsAction(): Action
    {
        return SubscriptionChangeActions::changeSelections()->size('sm')->color('gray');
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
}
