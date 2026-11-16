<?php

namespace App\Services;

use App\Support\QuestionSync;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The test server's calls to the live server's question sharing, every one
 * signed with the shared secret (QuestionSync) — the one way the test
 * server talks to it. A list asked for twice in one request is asked once.
 */
class QuestionSyncClient
{
    /**
     * @var array<string, mixed>
     */
    private array $answered = [];

    /**
     * What the live server answered, as JSON.
     *
     * @param  array<string, int|string>  $query
     *
     * @throws RuntimeException when this isn't the test server, or the live server can't be reached or refuses
     */
    public function get(string $path, array $query = []): mixed
    {
        $key = $path.'?'.http_build_query($query);

        return $this->answered[$key] ??= $this->send($path, $query)->json();
    }

    /**
     * An image's bytes — null when the live server has no such image.
     *
     * @throws RuntimeException as get() does
     */
    public function image(string $path): ?string
    {
        $response = $this->send('image', ['path' => $path], allowMissing: true);

        return $response->status() === 404 ? null : $response->body();
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function send(string $path, array $query, bool $allowMissing = false): Response
    {
        if (! QuestionSync::isConsumer() || QuestionSync::sourceUrl() === '' || QuestionSync::secret() === '') {
            throw new RuntimeException(__('This server is not set up to fetch from the live server (QUESTION_SYNC_ROLE, QUESTION_SYNC_SOURCE_URL and QUESTION_SYNC_SECRET in .env).'));
        }

        try {
            $response = Http::withHeaders(QuestionSync::headers('GET', $path, $query))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(60)
                ->retry(2, 1000, fn ($exception): bool => $exception instanceof ConnectionException, throw: false)
                ->get(QuestionSync::sourceUrl().'/api/'.QuestionSync::PREFIX.'/'.$path, $query);
        } catch (ConnectionException) {
            throw new RuntimeException(__('The live server could not be reached.'));
        }

        return match (true) {
            $response->successful(), $allowMissing && $response->status() === 404 => $response,
            $response->status() === 403 => throw new RuntimeException(__('Question sharing is closed on the live server. Its Super Admin has to open it first.')),
            $response->status() === 401 => throw new RuntimeException(__('The live server refused the secret — QUESTION_SYNC_SECRET must be the same on both servers.')),
            default => throw new RuntimeException(__('The live server refused the request (:status).', ['status' => $response->status()])),
        };
    }
}
