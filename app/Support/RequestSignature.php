<?php

namespace App\Support;

/**
 * How a request between two servers is signed and checked, whatever the
 * two are (a school's software — SchoolConnectionSignature; the live and
 * the test server — QuestionSync): both hold one secret and sign
 * "{timestamp}.{what is signed}" with HMAC-SHA256. The secret itself never
 * travels, and a request older than TOLERANCE_SECONDS is refused, so a
 * captured one can't be replayed later.
 */
final class RequestSignature
{
    /**
     * A technical limit, not a business number.
     */
    public const TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, string $timestamp, string $signed): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$signed}", $secret);
    }

    /**
     * True when the signature matches and the request is recent.
     */
    public static function verify(string $secret, ?string $timestamp, string $signed, ?string $signature): bool
    {
        if (blank($secret) || blank($timestamp) || blank($signature) || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $signed), $signature);
    }
}
