<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an account an Admin has not approved yet (a Staff member, a School
 * — User::isAwaitingApproval()) on its panel's "awaiting approval" page:
 * every other page of the panel sends it there. Each such panel has that
 * page at the `pending-approval` slug.
 */
class EnsureAccountIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $panelId = Filament::getCurrentPanel()?->getId();

        if ($user?->isAwaitingApproval()
            && ! $request->routeIs("filament.{$panelId}.pages.pending-approval")
            && ! $request->routeIs("filament.{$panelId}.auth.logout")) {
            return redirect()->route("filament.{$panelId}.pages.pending-approval");
        }

        return $next($request);
    }
}
