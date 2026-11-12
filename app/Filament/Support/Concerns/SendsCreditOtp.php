<?php

namespace App\Filament\Support\Concerns;

use App\Services\OtpService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * The "Send code" button of a payment form (ManualPaymentFields): sends the
 * code that spending wallet credit needs to the user's phone.
 *
 * @mixin NotifiesValidationFailures
 */
trait SendsCreditOtp
{
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
