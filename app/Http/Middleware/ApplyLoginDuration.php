<?php

namespace App\Http\Middleware;

use App\Models\BillingSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the session last as long as the Admin says people stay signed in.
 * Global, so that it has run before any route's StartSession — on every
 * panel, the Livewire requests and the plain pages alike.
 */
class ApplyLoginDuration
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.lifetime' => BillingSetting::current()->loginDurationInMinutes()]);

        return $next($request);
    }
}
