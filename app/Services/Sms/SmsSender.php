<?php

namespace App\Services\Sms;

/**
 * Whatever delivers a text message. The SMS provider isn't chosen yet, so
 * the application is bound to LogSmsSender for now; integrating one means
 * writing a class for it and changing the binding in AppServiceProvider —
 * nothing that sends a message has to change.
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
