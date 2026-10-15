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
     * When they haven't picked one (or something unsupported is stored),
     * the page's own default applies if it has one — the Admin panel opens
     * in English — and otherwise the configured application locale.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $defaultLocale  A default of the route's own, given as `SetLocale::class.':en'`.
     */
    public function handle(Request $request, Closure $next, ?string $defaultLocale = null): Response
    {
        $chosen = $request->hasSession()
            ? Locale::tryFrom((string) $request->session()->get(self::SESSION_KEY))
            : null;

        $locale = $chosen ?? Locale::tryFrom((string) $defaultLocale);

        if ($locale !== null) {
            app()->setLocale($locale->value);
        }

        return $next($request);
    }
}
