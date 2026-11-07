<?php

namespace App\Services;

use App\Enums\AdminNotificationTopic;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PlanChangeRefundMethod;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\Widgets\DashboardStat;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * What the bell says about a subscription purchase, and to whom — called
 * by SubscriptionPurchaseService once a step has really happened, so a
 * double click or a callback arriving twice says it once:
 *
 *  - placed: the Admins chosen for purchases learn of it (a payment to
 *    check, or one already paid with wallet credit), and a buyer whose
 *    payment waits is told it arrived;
 *  - approved / rejected / refunded: the buyer is told, with why when it
 *    was turned down or given back;
 *  - granted: the user the Super Admin gave a plan to is told.
 *
 * The free trial says nothing. Every text is written inside the closure
 * BellNotifier calls, so it comes out in its recipient's language; links
 * are kept as paths, so a stored notification follows the site wherever
 * it is served from.
 */
final class PurchaseNotifications
{
    public function __construct(private readonly BellNotifier $bell) {}

    public function placed(Payment $payment): void
    {
        if ($this->isTrial($payment)) {
            return;
        }

        $waiting = $payment->status === PaymentStatus::Pending;

        $this->bell->toAdmins(AdminNotificationTopic::Purchases, fn (): Notification => Notification::make()
            ->title($waiting
                ? __(':name (:role) asks for the :plan plan', $this->details($payment))
                : __(':name (:role) bought the :plan plan', $this->details($payment)))
            ->body($waiting
                ? __(':amount by :provider from :number — transaction ID :transaction. Check it and approve.', $this->details($payment))
                : __('Paid with wallet credit (:credit) — it is active already.', $this->details($payment)))
            ->icon($waiting ? 'heroicon-o-banknotes' : 'heroicon-o-check-badge')
            ->iconColor($waiting ? 'warning' : 'success')
            ->actions([
                Action::make('open')
                    ->label(__('Open payments'))
                    ->url(PaymentResource::getUrl('index', ['filters' => ['status' => ['value' => $payment->status->value]], 'search' => $payment->user->name], isAbsolute: false, panel: 'admin'))
                    ->markAsRead(),
            ]));

        if ($waiting) {
            $this->toBuyer($payment, fn (): Notification => Notification::make()
                ->title(__('Your payment was received'))
                ->body(__('We are checking your :amount payment for the :plan plan; the plan starts as soon as it is confirmed.', $this->details($payment)))
                ->icon('heroicon-o-clock')
                ->iconColor('info'));
        }
    }

    public function approved(Payment $payment): void
    {
        if ($this->isTrial($payment)) {
            return;
        }

        $this->toBuyer($payment, fn (): Notification => Notification::make()
            ->title(__('Your :plan plan is active', $this->details($payment)))
            ->body(($endsAt = $payment->subscription?->ends_at) ? __('It runs until :date.', ['date' => $endsAt->translatedFormat('j F Y')]) : null)
            ->icon('heroicon-o-check-badge')
            ->iconColor('success'));
    }

    /**
     * The Super Admin gave the plan — said even for the trial, since the
     * user didn't ask for it and wouldn't otherwise know.
     */
    public function granted(Payment $payment): void
    {
        $this->toBuyer($payment, fn (): Notification => Notification::make()
            ->title(__('You have been given the :plan plan', $this->details($payment)))
            ->body(($endsAt = $payment->subscription?->ends_at) ? __('It runs until :date.', ['date' => $endsAt->translatedFormat('j F Y')]) : null)
            ->icon('heroicon-o-gift')
            ->iconColor('success'));
    }

    /**
     * The owner asked to move to another plan: the Admins chosen for
     * purchases have money to check or send back, and the owner learns it
     * arrived.
     */
    public function planChangeRequested(Payment $payment): void
    {
        $refund = (float) $payment->refund_amount > 0;
        $details = $this->details($payment);

        $this->bell->toAdmins(AdminNotificationTopic::Purchases, fn (): Notification => Notification::make()
            ->title(__(':name (:role) asks to change :from to :plan', $details))
            ->body($refund
                ? __('Send back :refund to :provider :number, then approve.', $details)
                : __(':amount by :provider from :number — transaction ID :transaction. Check it and approve.', $details))
            ->icon('heroicon-o-arrows-right-left')
            ->iconColor('warning')
            ->actions([
                Action::make('open')
                    ->label(__('Open payments'))
                    ->url(PaymentResource::getUrl('index', ['filters' => ['status' => ['value' => $payment->status->value]], 'search' => $payment->user->name], isAbsolute: false, panel: 'admin'))
                    ->markAsRead(),
            ]));

        $this->toBuyer($payment, fn (): Notification => Notification::make()
            ->title(__('Your plan change was received'))
            ->body($refund
                ? __('Your plan changes from :from to :plan as soon as :refund is sent back to you.', $details)
                : __('Your plan changes from :from to :plan as soon as your :amount payment is confirmed.', $details))
            ->icon('heroicon-o-clock')
            ->iconColor('info'));
    }

    /**
     * The subscription moved to its new plan — approved by an Admin,
     * made by the Super Admin, or made at once with no money either way.
     */
    public function planChanged(Payment $payment): void
    {
        $details = $this->details($payment);
        $endsAt = $payment->subscription?->ends_at;

        $lines = array_filter([
            $endsAt ? __('It runs until :date.', ['date' => $endsAt->translatedFormat('j F Y')]) : null,
            match ($payment->refund_method) {
                PlanChangeRefundMethod::Wallet => __(':refund was added to your wallet.', $details),
                PlanChangeRefundMethod::MobileBanking => __(':refund was sent to your :provider.', $details),
                default => null,
            },
            $payment->gateway === PaymentGateway::Admin ? $payment->review_note : null,
        ]);

        $this->toBuyer($payment, fn (): Notification => Notification::make()
            ->title(__('Your plan was changed from :from to :plan', $details))
            ->body($lines === [] ? null : implode(' ', $lines))
            ->icon('heroicon-o-arrows-right-left')
            ->iconColor('success'));
    }

    /**
     * The Super Admin corrected the classes/subjects picked on a
     * subscription.
     */
    public function selectionsChanged(Subscription $subscription, string $note): void
    {
        $this->bell->toUser($subscription->user, fn (User $owner): Notification => Notification::make()
            ->title(__('The classes or subjects of your :plan plan were changed', ['plan' => (string) $subscription->plan?->name]))
            ->body($note)
            ->icon('heroicon-o-pencil-square')
            ->iconColor('info')
            ->actions([$this->mySubscriptionAction($owner)]));
    }

    public function rejected(Payment $payment): void
    {
        $this->toBuyer($payment, fn (): Notification => Notification::make()
            ->title(__('Your payment for the :plan plan was not accepted', $this->details($payment)))
            ->body($this->withCreditReturned($payment, $payment->review_note ?: __('The payment could not be found. Check the transaction ID and ask again.')))
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger'));
    }

    public function refunded(Payment $payment): void
    {
        $this->toBuyer($payment, fn (): Notification => Notification::make()
            ->title(__('Your :plan plan was refunded', $this->details($payment)))
            ->body($this->withCreditReturned($payment, $payment->review_note ?: __('The plan has ended and your money was given back.')))
            ->icon('heroicon-o-arrow-uturn-left')
            ->iconColor('warning'));
    }

    /**
     * To the buyer, with the way to their subscription page.
     *
     * @param  Closure(): Notification  $notification  Writes it — called in the buyer's language.
     */
    private function toBuyer(Payment $payment, Closure $notification): void
    {
        $this->bell->toUser($payment->user, fn (User $buyer): Notification => $notification()->actions([
            $this->mySubscriptionAction($buyer),
        ]));
    }

    private function mySubscriptionAction(User $user): Action
    {
        return Action::make('open')
            ->label(__('My subscription'))
            ->url(route("filament.{$user->role->panelId()}.pages.my-subscription", absolute: false))
            ->markAsRead();
    }

    private function withCreditReturned(Payment $payment, string $text): string
    {
        return (float) $payment->wallet_amount > 0
            ? $text.' '.__('The :credit of wallet credit was given back.', $this->details($payment))
            : $text;
    }

    private function isTrial(Payment $payment): bool
    {
        return (bool) $payment->plan?->is_trial;
    }

    /**
     * @return array<string, string>
     */
    private function details(Payment $payment): array
    {
        return [
            'name' => $payment->user->name,
            'role' => (string) $payment->user->role->getLabel(),
            'plan' => (string) $payment->plan?->name,
            'from' => (string) $payment->previousPlan?->name,
            'amount' => DashboardStat::money($payment->amount),
            'refund' => DashboardStat::money((float) $payment->refund_amount),
            'credit' => DashboardStat::money($payment->wallet_amount),
            'provider' => (string) ($payment->payer_provider?->getLabel() ?? '—'),
            'number' => (string) ($payment->payer_number ?? '—'),
            'transaction' => (string) ($payment->gateway_transaction_id ?? '—'),
        ];
    }
}
