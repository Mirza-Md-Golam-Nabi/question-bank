<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Stand-in until a real SMS provider is integrated: writes the message to
 * the log instead of sending it. Nothing reaches the phone, so the Admin's
 * "OTP required" setting must stay off while this is the bound sender.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS not sent (no provider integrated yet).', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
