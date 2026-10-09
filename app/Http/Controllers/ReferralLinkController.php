<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReferralLinkController extends Controller
{
    public const SESSION_KEY = 'referral_code';

    /**
     * Where a shared referral link lands. Google sign-in has no form to
     * type a code into, so the code is remembered here and picked up when
     * the visitor's account is created (GoogleAuthController); they then
     * choose their own role on the home page.
     */
    public function __invoke(Request $request, string $code, ReferralService $referrals): RedirectResponse
    {
        if ($referrals->isEnabled() && $referrals->findReferrer($code)) {
            $request->session()->put(self::SESSION_KEY, $code);
        }

        return redirect()->route('home');
    }
}
