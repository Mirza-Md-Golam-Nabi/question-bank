<?php

namespace App\Filament\Support;

use App\Enums\MobileBankingProvider;
use App\Enums\UserRole;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PlanConditions;
use App\Services\SubscriptionPurchaseService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Super Admin's "Give a subscription" window — from the subscriptions
 * list (any Teacher, Student or School, picked in the window) or from a
 * Teacher's or School's row (that user). It only collects the form; what
 * giving means — who may, which plans, the payment it records — is all
 * `SubscriptionPurchaseService::grant()`'s.
 */
final class GrantSubscriptionAction
{
    use NotifiesValidationFailures;

    /**
     * The plans asked for while the window is open, kept for the request.
     *
     * @var array<int, ?SubscriptionPlan>
     */
    private array $plans = [];

    /**
     * From the subscriptions list: the user is picked in the window.
     */
    public static function make(): Action
    {
        return (new self)->action(forRecord: false);
    }

    /**
     * On a user's row: for that user.
     */
    public static function forRecord(): Action
    {
        return (new self)->action(forRecord: true);
    }

    private function action(bool $forRecord): Action
    {
        $action = Action::make('grantSubscription')
            ->label(__('Give a subscription'))
            ->icon(Heroicon::OutlinedGift)
            ->visible(fn (): bool => app(SubscriptionPurchaseService::class)->canGrant(auth()->user()))
            ->modalHeading(fn (?User $record): string => $forRecord && $record
                ? __('Give :name a subscription', ['name' => $record->name])
                : __('Give a subscription'))
            ->modalSubmitActionLabel(__('Give'))
            ->schema(fn (?User $record): array => $this->form($forRecord ? $record : null))
            ->action(function (array $data, ?User $record, Action $action) use ($forRecord): void {
                $user = $forRecord ? $record : User::find($data['user_id'] ?? null);
                $plan = SubscriptionPlan::find($data['plan_id'] ?? null);

                if (! $user || ! $plan) {
                    return;
                }

                $payment = $this->rescueWithNotification(
                    fn () => app(SubscriptionPurchaseService::class)->grant(auth()->user(), $user, $plan, $data),
                    $action,
                );

                if (! $payment) {
                    return;
                }

                Notification::make()->title(__('Subscription given to :name.', ['name' => $user->name]))->success()->send();
            });

        return $forRecord ? $action->iconButton()->color('success') : $action;
    }

    /**
     * @return array<int, mixed>
     */
    private function form(?User $user): array
    {
        $userFrom = fn (Get $get): ?User => $user ?? User::find($get('user_id'));
        $isGift = fn (Get $get): bool => (bool) $get('gift');

        return [
            Select::make('user_id')
                ->label(__('User'))
                ->helperText(__('A Teacher, Student or School — search by name, email or phone.'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => self::subscribers()
                    ->matching($search)
                    ->orderBy('name')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (User $user): array => [$user->id => self::userLabel($user)])
                    ->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => ($found = self::subscribers()->find($value)) ? self::userLabel($found) : null)
                ->visible($user === null)
                ->required($user === null)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('plan_id', null)),

            Select::make('plan_id')
                ->label(__('Plan'))
                ->options(fn (Get $get): array => ($subscriber = $userFrom($get))
                    ? app(SubscriptionPurchaseService::class)->grantablePlansFor($subscriber)->get()
                        ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->id => self::planLabel($plan)])
                        ->all()
                    : [])
                ->helperText(__('Any plan of their role — also one off sale, and the trial again.'))
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $set('class_ids', null);
                    $set('subject_ids', null);
                    $set('amount', $this->plan($state)?->price);
                }),

            ...(new PlanPickFields)->components(
                fn (Get $get): PlanConditions => $this->plan($get('plan_id'))?->planConditions() ?? new PlanConditions,
                __("A student's profile class changes to the one chosen."),
            ),

            TextInput::make('days')
                ->label(__('Length (days)'))
                ->helperText(__("Leave empty for the plan's own length."))
                ->integer()
                ->minValue(1),

            Toggle::make('gift')
                ->label(__('Free — a gift'))
                ->helperText(__('A gift earns no referral reward, cannot be refunded, and does not make them a paying customer.'))
                ->live(),

            TextInput::make('amount')
                ->label(__('Amount taken'))
                ->prefix('৳')
                ->numeric()
                ->minValue(0.01)
                ->helperText(__('Taken outside the system. The referrer earns on it as on a purchase, and it can be refunded in the refund period.'))
                ->hidden($isGift)
                ->required(fn (Get $get): bool => ! $isGift($get)),

            Select::make('payer_provider')
                ->label(__('Taken with'))
                ->options(MobileBankingProvider::class)
                ->placeholder(__('Cash / other'))
                ->hidden($isGift),

            TextInput::make('transaction_id')
                ->label(__('Transaction ID'))
                ->maxLength(100)
                ->hidden($isGift),

            Textarea::make('note')
                ->label(__('Reason / note'))
                ->rows(2)
                ->maxLength(255)
                ->required(),
        ];
    }

    private function plan(mixed $id): ?SubscriptionPlan
    {
        if (! filled($id)) {
            return null;
        }

        return $this->plans[(int) $id] ??= SubscriptionPlan::find((int) $id);
    }

    /**
     * @return Builder<User>
     */
    private static function subscribers(): Builder
    {
        return User::query()->whereIn('role', UserRole::subscribers());
    }

    private static function userLabel(User $user): string
    {
        return "{$user->name} ({$user->email}) · {$user->role->getLabel()}";
    }

    private static function planLabel(SubscriptionPlan $plan): string
    {
        return collect([
            $plan->name,
            '৳'.number_format((float) $plan->price),
            $plan->durationLabel(),
            $plan->is_trial ? __('Trial') : null,
            $plan->is_active ? null : __('Off sale'),
        ])->filter()->implode(' · ');
    }
}
