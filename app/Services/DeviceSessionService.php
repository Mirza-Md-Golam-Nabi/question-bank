<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The devices a Teacher or Student is signed in on — the only place the
 * device limit lives. Signing in on one more device than the Admin allows
 * (BillingSetting::teacher_device_limit / student_device_limit, 0 = no
 * limit) signs the account out of the device used longest ago, so one
 * account can't be passed round a class. The user can see their devices on
 * their profile and sign any of them out.
 *
 * Works on the `sessions` table, so only with the database session driver;
 * with any other driver it does nothing.
 */
class DeviceSessionService
{
    public function limitFor(User $user): ?int
    {
        $limit = match ($user->role) {
            UserRole::Teacher => BillingSetting::current()->teacher_device_limit,
            UserRole::Student => BillingSetting::current()->student_device_limit,
            default => 0,
        };

        return $limit > 0 ? $limit : null;
    }

    /**
     * Called right after a sign-in: keeps the current device and as many
     * of the most recently used others as the limit leaves room for.
     */
    public function enforceLimit(User $user, string $currentSessionId): void
    {
        $limit = $this->limitFor($user);

        if ($limit === null || ! $this->tracksDevices()) {
            return;
        }

        $signedOut = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->orderByDesc('last_activity')
            ->pluck('id')
            ->slice($limit - 1)
            ->values();

        if ($signedOut->isNotEmpty()) {
            DB::table('sessions')->whereIn('id', $signedOut)->delete();

            Log::info('Signed out of older devices: device limit reached.', [
                'user_id' => $user->id,
                'devices_signed_out' => $signedOut->count(),
            ]);
        }
    }

    /**
     * The devices the user is signed in on, most recently used first.
     *
     * @return Collection<int, array{id: string, ip: ?string, agent: string, last_active: Carbon, is_current: bool}>
     */
    public function devicesOf(User $user, string $currentSessionId): Collection
    {
        if (! $this->tracksDevices()) {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'ip' => $session->ip_address,
                'agent' => $this->describeAgent((string) $session->user_agent),
                'last_active' => Carbon::createFromTimestamp($session->last_activity),
                'is_current' => $session->id === $currentSessionId,
            ]);
    }

    /**
     * Signs one of the user's own devices out (never someone else's).
     */
    public function signOut(User $user, string $sessionId): void
    {
        if ($this->tracksDevices()) {
            DB::table('sessions')->where('user_id', $user->id)->where('id', $sessionId)->delete();
        }
    }

    private function tracksDevices(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * "Chrome on Android" from a user-agent string — enough to recognise a
     * device, not a full parser.
     */
    private function describeAgent(string $agent): string
    {
        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'])
            ->first(fn (string $name, string $marker): bool => str_contains($agent, $marker), __('Browser'));
        $system = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'Mac', 'Linux' => 'Linux'])
            ->first(fn (string $name, string $marker): bool => str_contains($agent, $marker), __('unknown device'));

        return __(':browser on :system', ['browser' => $browser, 'system' => $system]);
    }
}
