<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SESSION_KEY = 'locale';

    private const SITE_LOCALE_ATTRIBUTE = 'site_locale';

    /**
     * Pages that keep a language choice of their own, apart from the
     * panels' — the exam pages a share link opens, which start in the
     * site's own language whatever the same browser picked in a panel.
     *
     * @var array<int, string>
     */
    public const SCOPES = ['exam'];

    /**
     * Where the session keeps the choice for a scope of pages (none: the
     * panels' and the rest of the site's).
     */
    public static function sessionKey(?string $scope = null): string
    {
        return in_array($scope, self::SCOPES, true) ? "{$scope}_locale" : self::SESSION_KEY;
    }

    /**
     * Applies the language the visitor picked from the language switcher.
     * When they haven't picked one (or something unsupported is stored),
     * the page's own default applies if it has one — the Admin panel opens
     * in English — and otherwise the configured application locale.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $defaultLocale  A default of the route's own, given as `SetLocale::class.':en'`.
     * @param  string|null  $scope  Pages with a choice of their own (SCOPES), given as `SetLocale::class.':,exam'`.
     */
    public function handle(Request $request, Closure $next, ?string $defaultLocale = null, ?string $scope = null): Response
    {
        // setLocale() rewrites config('app.locale'), so the site's own
        // language is noted before the first change of this request.
        if (! $request->attributes->has(self::SITE_LOCALE_ATTRIBUTE)) {
            $request->attributes->set(self::SITE_LOCALE_ATTRIBUTE, config('app.locale'));
        }

        $chosen = $request->hasSession()
            ? Locale::tryFrom((string) $request->session()->get(self::sessionKey($scope)))
            : null;

        // Pages with a choice of their own go back to the site's language
        // when there is none — not the panels' choice set just before.
        $locale = $chosen
            ?? Locale::tryFrom((string) $defaultLocale)
            ?? ($scope ? Locale::tryFrom((string) $request->attributes->get(self::SITE_LOCALE_ATTRIBUTE)) : null);

        if ($locale !== null) {
            app()->setLocale($locale->value);
        }

        return $next($request);
    }
}
