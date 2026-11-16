<?php

namespace App\Support;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What a server is in bringing questions from the live server to the test
 * server, and how their requests are signed — the one place both sides
 * read it from (config `question-bank.question_sync`):
 *
 *  - the live server is the "source": it only shares (QuestionSyncSource),
 *    and has no fetch page;
 *  - the test server is the "consumer": it only fetches
 *    (QuestionSyncImporter), and shares nothing — so nothing ever goes the
 *    other way;
 *  - only the Super Admin opens the sharing or fetches.
 *
 * A request is signed (RequestSignature) over its method, its path after
 * the API's prefix and its query, in sorted order — so a signature for one
 * question list can't be used for another.
 */
final class QuestionSync
{
    public const SOURCE = 'source';

    public const CONSUMER = 'consumer';

    /**
     * What the API's routes start with, after `/api/`.
     */
    public const PREFIX = 'question-sync';

    public const TIMESTAMP_HEADER = 'X-Timestamp';

    public const SIGNATURE_HEADER = 'X-Signature';

    /**
     * The image files a question can have — the only files the live server
     * hands over.
     *
     * @var list<string>
     */
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    public static function isSource(): bool
    {
        return config('question-bank.question_sync.role') === self::SOURCE;
    }

    public static function isConsumer(): bool
    {
        return config('question-bank.question_sync.role') === self::CONSUMER;
    }

    public static function secret(): string
    {
        return (string) config('question-bank.question_sync.secret');
    }

    /**
     * The live server's address, without a trailing slash.
     */
    public static function sourceUrl(): string
    {
        return rtrim((string) config('question-bank.question_sync.source_url'), '/');
    }

    /**
     * Whether this person may open the sharing (live) or fetch (test).
     */
    public static function canManage(?User $user): bool
    {
        return $user?->role === UserRole::SuperAdmin;
    }

    /**
     * What a request to `$path` (after the prefix) with `$query` signs.
     *
     * @param  array<string, mixed>  $query
     */
    public static function signed(string $method, string $path, array $query): string
    {
        ksort($query);

        return strtoupper($method).' '.trim($path, '/').'?'.http_build_query($query);
    }

    /**
     * The headers of a signed request.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function headers(string $method, string $path, array $query): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => RequestSignature::sign(self::secret(), $timestamp, self::signed($method, $path, $query)),
        ];
    }

    /**
     * Whether a request that reached this server is signed with the secret.
     */
    public static function verify(Request $request): bool
    {
        return RequestSignature::verify(
            self::secret(),
            $request->header(self::TIMESTAMP_HEADER),
            self::signed($request->getMethod(), Str::after($request->getPathInfo(), '/'.self::PREFIX.'/'), $request->query()),
            $request->header(self::SIGNATURE_HEADER),
        );
    }

    /**
     * Until when the sharing is open, as both servers' pages write it — in
     * the viewer's language, digits too.
     */
    public static function displayTime(CarbonInterface $time): string
    {
        return Locale::current()->writeDigits($time->copy()->timezone(config('app.timezone'))->locale(app()->getLocale())->translatedFormat('j M Y, g:i A'));
    }

    /**
     * Whether a path on the public disk is one an image of a question can
     * be at — a plain relative path to an image file, never out of the disk.
     */
    public static function isImagePath(mixed $path): bool
    {
        return is_string($path)
            && ! str_contains($path, '..')
            && preg_match('#^[A-Za-z0-9][A-Za-z0-9_\-/]*\.('.implode('|', self::IMAGE_EXTENSIONS).')$#i', $path) === 1;
    }
}
