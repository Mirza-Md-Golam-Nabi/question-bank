<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Filament\Auth\Http\Responses\LogoutResponse as FilamentLogoutResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Where a user lands after logging out. Teachers, Staff and Students go to
 * the home page, where every panel's sign-in is one click away; the Admin
 * panel keeps Filament's default and returns to its own login form.
 */
class LogoutResponse extends FilamentLogoutResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === UserRole::Admin->panelId()) {
            return parent::toResponse($request);
        }

        return redirect()->route('home');
    }
}
