<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Remembers the chosen language in the session — for the pages it was
     * chosen on, when they keep their own (`?for=exam`) — and returns the
     * visitor to the page they switched from.
     */
    public function __invoke(Request $request, Locale $locale): RedirectResponse
    {
        $request->session()->put(SetLocale::sessionKey($request->query('for')), $locale->value);

        return back();
    }
}
