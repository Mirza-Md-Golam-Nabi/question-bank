<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SESSION_KEY = 'locale';

    /**
     * Applies the language the visitor picked from the language switcher.
     * Falls back to the configured application locale when nothing (or
     * something unsupported) is stored in the session.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $locale = Locale::tryFrom((string) $request->session()->get(self::SESSION_KEY));

            if ($locale !== null) {
                app()->setLocale($locale->value);
            }
        }

        return $next($request);
    }
}
