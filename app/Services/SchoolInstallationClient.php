<?php

namespace App\Services;

use App\Models\SchoolProfile;
use App\Support\SchoolConnectionSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls a School's installation of the school management software, every
 * request signed with the School's secret (SchoolConnectionSignature) — the
 * one way this platform talks to a school's software.
 */
class SchoolInstallationClient
{
    /**
     * @return array<string, mixed> The JSON the installation answered with.
     *
     * @throws RuntimeException when the School isn't connected, or its software can't be reached or refuses
     */
    public function get(SchoolProfile $profile, string $path): array
    {
        return $this->send($profile, 'GET', $path, '')->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> The JSON the installation answered with.
     *
     * @throws RuntimeException as get() does
     */
    public function post(SchoolProfile $profile, string $path, array $body): array
    {
        return $this->send($profile, 'POST', $path, (string) json_encode($body))->json() ?? [];
    }

    private function send(SchoolProfile $profile, string $method, string $path, string $body): Response
    {
        if (! $profile->isConnected()) {
            throw new RuntimeException(__('The school has not connected its school management software yet.'));
        }

        try {
            $response = Http::withHeaders(SchoolConnectionSignature::headers($profile->connection_code, $profile->connection_secret, $body))
                ->withBody($body, 'application/json')
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->send($method, $profile->installation_url.$path);
        } catch (ConnectionException) {
            throw new RuntimeException(__('The school management software could not be reached.'));
        }

        if ($response->status() === 409) {
            throw new RuntimeException(__('This exam result is already published in the school management software, so its marks cannot be changed.'));
        }

        if ($response->failed()) {
            throw new RuntimeException(__('The school management software refused the request (:status).', ['status' => $response->status()]));
        }

        return $response;
    }
}
