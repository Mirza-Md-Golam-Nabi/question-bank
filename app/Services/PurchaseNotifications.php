<?php

namespace App\Services;

use App\Enums\AdminNotificationTopic;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\Widgets\DashboardStat;
use App\Models\Payment;
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
 *    was turned down or given back.
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
            Action::make('open')
                ->label(__('My subscription'))
                ->url(route("filament.{$buyer->role->panelId()}.pages.my-subscription", absolute: false))
                ->markAsRead(),
        ]));
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
            'amount' => DashboardStat::money($payment->amount),
            'credit' => DashboardStat::money($payment->wallet_amount),
            'provider' => (string) ($payment->payer_provider?->getLabel() ?? '—'),
            'number' => (string) ($payment->payer_number ?? '—'),
            'transaction' => (string) ($payment->gateway_transaction_id ?? '—'),
        ];
    }
}
