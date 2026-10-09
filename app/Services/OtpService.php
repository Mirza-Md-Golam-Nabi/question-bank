<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * One-time codes sent to a user's phone, asked for when they spend wallet
 * credit — which is what makes giving a real phone number worth it: credit
 * sitting behind a made-up number can't be used.
 *
 * Whether a code is asked for at all is the Admin's setting, and it is off
 * until an SMS provider is integrated.
 */
class OtpService
{
    private const CODE_TTL_MINUTES = 5;

    private const MAX_SENDS_PER_HOUR = 5;

    private const MAX_WRONG_ATTEMPTS = 5;

    public function __construct(private readonly SmsSender $sms) {}

    public function isRequiredForCredit(): bool
    {
        return BillingSetting::current()->otp_required_for_credit;
    }

    /**
     * @throws ValidationException When the user has no phone number, or has asked for too many codes.
     */
    public function send(User $user): void
    {
        if (blank($user->phone)) {
            throw ValidationException::withMessages([
                'otp_code' => __('Add your phone number to your profile first.'),
            ]);
        }

        if (RateLimiter::tooManyAttempts($this->sendLimiterKey($user), self::MAX_SENDS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'otp_code' => __('Too many codes requested. Try again later.'),
            ]);
        }

        RateLimiter::hit($this->sendLimiterKey($user), 3600);
        RateLimiter::clear($this->attemptLimiterKey($user));

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user), Hash::make($code), now()->addMinutes(self::CODE_TTL_MINUTES));

        $this->sms->send($user->phone, __('Your verification code is :code. It is valid for :minutes minutes.', [
            'code' => $code,
            'minutes' => self::CODE_TTL_MINUTES,
        ]));
    }

    /**
     * A code works once, for the phone number it was sent to, and only a
     * few wrong guesses are allowed against it.
     */
    public function verify(User $user, ?string $code): bool
    {
        $hash = Cache::get($this->cacheKey($user));

        if ($hash === null || blank($code) || RateLimiter::tooManyAttempts($this->attemptLimiterKey($user), self::MAX_WRONG_ATTEMPTS)) {
            return false;
        }

        if (! Hash::check(trim($code), $hash)) {
            RateLimiter::hit($this->attemptLimiterKey($user), self::CODE_TTL_MINUTES * 60);

            return false;
        }

        Cache::forget($this->cacheKey($user));

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return true;
    }

    /**
     * Keyed by the phone number too, so a code sent to one number is
     * worthless once the number on the profile has been changed.
     */
    private function cacheKey(User $user): string
    {
        return "otp:{$user->id}:{$user->phone}";
    }

    private function sendLimiterKey(User $user): string
    {
        return "otp-send:{$user->id}";
    }

    private function attemptLimiterKey(User $user): string
    {
        return "otp-attempt:{$user->id}";
    }
}
