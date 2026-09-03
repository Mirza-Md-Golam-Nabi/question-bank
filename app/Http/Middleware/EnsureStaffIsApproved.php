<?php

namespace App\Http\Middleware;

use App\Filament\Staff\Pages\PendingApprovalNotice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user?->isStaffPendingApproval()
            && ! $request->routeIs('filament.staff.pages.pending-approval')
            && ! $request->routeIs('filament.staff.auth.logout')) {
            return redirect()->to(PendingApprovalNotice::getUrl());
        }

        return $next($request);
    }
}
