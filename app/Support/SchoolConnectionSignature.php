<?php

namespace App\Support;

/**
 * Signs and checks the requests exchanged with a school's installation of
 * the school management software. Both sides hold the School's secret
 * (SchoolProfile::connection_secret) and sign "{timestamp}.{body}" with
 * HMAC-SHA256 — the secret itself never travels. The school management
 * software signs the same way (its QuestionBankSignature).
 */
class SchoolConnectionSignature
{
    /**
     * How old a signed request may be before it is refused, in seconds — so
     * a captured request can't be replayed later. A technical limit.
     */
    public const TOLERANCE_SECONDS = 300;

    public const CODE_HEADER = 'X-School-Code';

    public const TIMESTAMP_HEADER = 'X-Timestamp';

    public const SIGNATURE_HEADER = 'X-Signature';

    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    /**
     * True when the signature matches and the request is recent.
     */
    public static function verify(string $secret, ?string $timestamp, string $body, ?string $signature): bool
    {
        if (blank($timestamp) || blank($signature) || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), $signature);
    }

    /**
     * The longest a signed link may stay open, in seconds — a technical
     * limit on what the school's software may ask for.
     */
    public const MAX_LINK_SECONDS = 86400;

    /**
     * Signs a link the school's software opens in its own pages (its
     * students' "School questions"): the School's code with the moment the
     * link stops working. Signed apart from requests, so the one is never
     * taken for the other.
     */
    public static function signLink(string $secret, string $code, string $expires): string
    {
        return self::sign($secret, $expires, "link:{$code}");
    }

    /**
     * True when the link's signature matches and it hasn't expired — nor
     * claims to stay open longer than MAX_LINK_SECONDS.
     */
    public static function verifyLink(string $secret, string $code, ?string $expires, ?string $signature): bool
    {
        if (blank($expires) || blank($signature) || ! ctype_digit($expires)) {
            return false;
        }

        if ((int) $expires < now()->timestamp || (int) $expires > now()->timestamp + self::MAX_LINK_SECONDS) {
            return false;
        }

        return hash_equals(self::signLink($secret, $code, $expires), $signature);
    }

    /**
     * The headers of a request signed with this secret.
     *
     * @return array<string, string>
     */
    public static function headers(string $code, string $secret, string $body): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            self::CODE_HEADER => $code,
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => self::sign($secret, $timestamp, $body),
        ];
    }
}
