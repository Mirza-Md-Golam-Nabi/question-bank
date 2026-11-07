<?php

namespace App\Filament\Support;

use App\Enums\MobileBankingProvider;
use App\Enums\PlanChangeRefundMethod;
use App\Enums\UserRole;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Models\BillingSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PlanConditions;
use App\Services\SubscriptionChangeService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * "Change plan" and "Change classes/subjects" — for the owner on their
 * "My Subscription" page and for the Super Admin on the subscriptions list.
 * Only the windows: who may, what it costs and what happens are all
 * SubscriptionChangeService's.
 */
final class SubscriptionChangeActions
{
    use NotifiesValidationFailures;

    /**
     * @var array<int, ?SubscriptionPlan>
     */
    private array $plans = [];

    /**
     * The owner's subscriptions asked for while the window is open.
     *
     * @var array<int, ?Subscription>
     */
    private array $ownSubscriptions = [];

    /**
     * @param  bool  $asSuperAdmin  On the Admin's list (the row's subscription) rather than the owner's page (`subscription` argument).
     */
    private function __construct(private readonly bool $asSuperAdmin) {}

    public static function changePlan(bool $asSuperAdmin = false): Action
    {
        return (new self($asSuperAdmin))->changePlanAction();
    }

    public static function changeSelections(bool $asSuperAdmin = false): Action
    {
        return (new self($asSuperAdmin))->changeSelectionsAction();
    }

    private function changePlanAction(): Action
    {
        $service = fn (): SubscriptionChangeService => app(SubscriptionChangeService::class);

        return Action::make('changePlan')
            ->label(__('Change plan'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (?Subscription $record, array $arguments): bool => ($subscription = $this->subscriptionFrom($record, $arguments)) !== null
                && $service()->canChangePlan(auth()->user(), $subscription))
            ->modalHeading(fn (?Subscription $record, array $arguments): string => __('Change :plan to another plan', ['plan' => $this->subscriptionFrom($record, $arguments)?->plan?->name]))
            ->modalSubmitActionLabel(__('Change plan'))
            ->schema(fn (?Subscription $record, array $arguments): array => $this->changePlanForm($this->subscriptionFrom($record, $arguments)))
            ->action(function (array $data, ?Subscription $record, array $arguments, Action $action) use ($service): void {
                $subscription = $this->subscriptionFrom($record, $arguments);
                $plan = $this->plan($data['plan_id'] ?? null);

                if (! $subscription || ! $plan) {
                    return;
                }

                $payment = $this->rescueWithNotification(fn () => $this->asSuperAdmin
                    ? $service()->changeAsSuperAdmin(auth()->user(), $subscription, $plan, $data)
                    : $service()->request(auth()->user(), $subscription, $plan, $data), $action);

                if (! $payment) {
                    return;
                }

                Notification::make()
                    ->title($payment->isSuccessful()
                        ? __('The plan was changed to :plan.', ['plan' => $plan->name])
                        : __('Your request was sent. The plan changes as soon as the Admin approves it.'))
                    ->success()
                    ->send();
            });
    }

    private function changeSelectionsAction(): Action
    {
        $service = fn (): SubscriptionChangeService => app(SubscriptionChangeService::class);

        return Action::make('changeSelections')
            ->label(__('Change classes/subjects'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (?Subscription $record, array $arguments): bool => ($subscription = $this->subscriptionFrom($record, $arguments)) !== null
                && $service()->canChangeSelections(auth()->user(), $subscription))
            ->modalHeading(__('Change classes/subjects'))
            ->modalDescription($this->asSuperAdmin
                ? __('Exams already made stay as they are; what is left of the subscription is for the new picks.')
                : __('Possible only until you have used the subscription.'))
            ->fillForm(fn (?Subscription $record, array $arguments): array => $this->currentSelections($this->subscriptionFrom($record, $arguments)))
            ->schema(fn (?Subscription $record, array $arguments): array => [
                ...$this->pickFields(fn (): PlanConditions => PlanConditions::fromArray($this->subscriptionFrom($record, $arguments)?->conditions)),
                ...$this->asSuperAdmin ? [$this->noteField()] : [],
            ])
            ->action(function (array $data, ?Subscription $record, array $arguments, Action $action) use ($service): void {
                $subscription = $this->subscriptionFrom($record, $arguments);

                if (! $subscription) {
                    return;
                }

                $changed = $this->rescueWithNotification(function () use ($service, $subscription, $data): bool {
                    $service()->changeSelections(auth()->user(), $subscription, $data);

                    return true;
                }, $action);

                if (! $changed) {
                    return;
                }

                Notification::make()->title(__('Classes and subjects changed.'))->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function changePlanForm(?Subscription $subscription): array
    {
        if (! $subscription) {
            return [];
        }

        $service = app(SubscriptionChangeService::class);
        $quote = fn (Get $get): ?array => ($plan = $this->plan($get('plan_id'))) ? $service->quote($subscription, $plan) : null;
        $pays = fn (Get $get): bool => ($quote($get)['pay'] ?? 0) > 0;
        $isRefund = fn (Get $get): bool => ($quote($get)['refund'] ?? 0) > 0;
        $isDowngrade = fn (Get $get): bool => ($plan = $this->plan($get('plan_id'))) !== null && (float) $plan->price < (float) $subscription->plan_price;
        $settings = BillingSetting::current();

        $planField = Select::make('plan_id')
            ->label(__('New plan'))
            ->options($service->plansToChangeTo(auth()->user(), $subscription)->get()
                ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->id => "{$plan->name} — ৳".number_format((float) $plan->price, 2).' · '.$plan->durationLabel()])
                ->all())
            ->required()
            ->live()
            ->afterStateUpdated(function (Set $set, mixed $state) use ($service, $subscription): void {
                $set('class_ids', null);
                $set('subject_ids', null);

                if ($plan = $this->plan($state)) {
                    $quote = $service->quote($subscription, $plan);
                    $set('amount', $quote['pay']);
                    $set('refund_amount', $quote['refund']);
                }
            });

        $summary = TextEntry::make('summary')
            ->hiddenLabel()
            ->visible(fn (Get $get): bool => $quote($get) !== null)
            ->state(fn (Get $get): ?HtmlString => ($current = $quote($get)) ? new HtmlString(view('filament.pages.partials.plan-change-summary', [
                'quote' => $current,
                'showPaymentInfo' => ! $this->asSuperAdmin,
                'receivingNumbers' => $settings->receivingNumbers(),
                'instructions' => $settings->payment_instructions,
            ])->render()) : null);

        $picks = $this->pickFields(fn (Get $get): PlanConditions => $this->plan($get('plan_id'))?->planConditions() ?? new PlanConditions);

        if ($this->asSuperAdmin) {
            return [
                $planField,
                ...$picks,
                $summary,
                TextInput::make('amount')
                    ->label(__('Amount taken'))
                    ->prefix('৳')
                    ->numeric()
                    ->minValue(0)
                    ->helperText(__('The difference, taken outside the system — 0 to waive it. Nobody earns commission on it.'))
                    ->visible(fn (Get $get): bool => $this->plan($get('plan_id')) !== null && ! $isDowngrade($get))
                    ->required(fn (Get $get): bool => $this->plan($get('plan_id')) !== null && ! $isDowngrade($get)),
                TextInput::make('refund_amount')
                    ->label(__('Amount given back'))
                    ->prefix('৳')
                    ->numeric()
                    ->minValue(0)
                    ->live(onBlur: true)
                    ->visible($isDowngrade)
                    ->required($isDowngrade),
                Radio::make('refund_method')
                    ->label(__('Given back'))
                    ->options($this->refundMethodOptions($subscription))
                    ->visible(fn (Get $get): bool => $isDowngrade($get) && (float) $get('refund_amount') > 0)
                    ->required(fn (Get $get): bool => $isDowngrade($get) && (float) $get('refund_amount') > 0),
                Select::make('payer_provider')
                    ->label(__('With'))
                    ->options(MobileBankingProvider::class)
                    ->placeholder(__('Cash / other'))
                    ->visible(fn (Get $get): bool => $this->plan($get('plan_id')) !== null),
                TextInput::make('transaction_id')
                    ->label(__('Transaction ID'))
                    ->maxLength(100)
                    ->visible(fn (Get $get): bool => $this->plan($get('plan_id')) !== null),
                $this->noteField(),
            ];
        }

        $providers = collect($settings->receivingNumbers())
            ->map(fn (string $number, string $provider): string => MobileBankingProvider::from($provider)->getLabel())
            ->all();

        return [
            $planField,
            ...$picks,
            $summary,
            Radio::make('payer_provider')
                ->label(fn (Get $get): string => $isRefund($get) ? __('Send my money by') : __('Sent with'))
                ->options($providers)
                ->inline()
                ->visible(fn (Get $get): bool => $pays($get) || $isRefund($get))
                ->required(fn (Get $get): bool => $pays($get) || $isRefund($get)),
            TextInput::make('payer_number')
                ->label(fn (Get $get): string => $isRefund($get) ? __('Number to send it to') : __('Number you sent the payment from'))
                ->tel()
                ->visible(fn (Get $get): bool => $pays($get) || $isRefund($get))
                ->required(fn (Get $get): bool => $pays($get) || $isRefund($get)),
            TextInput::make('transaction_id')
                ->label(__('Transaction ID'))
                ->maxLength(100)
                ->visible($pays)
                ->required($pays),
        ];
    }

    /**
     * @param  Closure(Get): PlanConditions  $conditions
     * @return array<int, Select>
     */
    private function pickFields(Closure $conditions): array
    {
        return (new PlanPickFields)->components(
            $conditions,
            $this->asSuperAdmin
                ? __("A student's profile class changes to the one chosen.")
                : (auth()->user()->role === UserRole::Student ? __('Your profile class changes to the one you choose.') : null),
        );
    }

    /**
     * Into the wallet only for a role that has one.
     *
     * @return array<string, string>
     */
    public static function refundMethodOptions(Subscription|User $for): array
    {
        $user = $for instanceof Subscription ? $for->user : $for;

        return collect(PlanChangeRefundMethod::cases())
            ->reject(fn (PlanChangeRefundMethod $method): bool => $method === PlanChangeRefundMethod::Wallet && ! $user->role->holdsWallet())
            ->mapWithKeys(fn (PlanChangeRefundMethod $method): array => [$method->value => $method->getLabel()])
            ->all();
    }

    private function noteField(): Textarea
    {
        return Textarea::make('note')
            ->label(__('Reason / note'))
            ->helperText(__('The user sees it in their bell.'))
            ->rows(2)
            ->maxLength(255)
            ->required();
    }

    /**
     * What is picked now, in the shape the pick fields take — one value
     * where the plan asks for one, a list where it asks for several.
     *
     * @return array<string, mixed>
     */
    private function currentSelections(?Subscription $subscription): array
    {
        if (! $subscription) {
            return [];
        }

        $conditions = PlanConditions::fromArray($subscription->conditions);
        $selections = $subscription->selections ?? [];
        $shape = fn (array $ids, ?int $count): mixed => $count === 1 ? ($ids[0] ?? null) : $ids;

        return [
            'class_ids' => $shape($selections['class_ids'] ?? [], $conditions->classPickCount),
            'subject_ids' => $shape($selections['subject_ids'] ?? [], $conditions->subjectPickCount),
        ];
    }

    /**
     * The subscription the action is for: the Admin's row, or one of the
     * owner's own (never someone else's) on their page.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function subscriptionFrom(?Subscription $record, array $arguments): ?Subscription
    {
        if ($this->asSuperAdmin) {
            return $record;
        }

        $id = (int) ($arguments['subscription'] ?? 0);

        return $this->ownSubscriptions[$id] ??= auth()->user()->subscriptions()->with('plan')->find($id);
    }

    private function plan(mixed $id): ?SubscriptionPlan
    {
        $id = (int) $id;

        if ($id === 0) {
            return null;
        }

        return $this->plans[$id] ??= SubscriptionPlan::find($id);
    }
}
