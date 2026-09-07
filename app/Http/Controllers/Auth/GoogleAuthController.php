<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    private const SESSION_KEY = 'google_auth_intended_role';

    public function redirect(string $role): RedirectResponse
    {
        $role = $this->googleOnlyRoleOrAbort($role);

        session([self::SESSION_KEY => $role->value]);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $intendedRole = $this->googleOnlyRoleOrAbort(session(self::SESSION_KEY));

        session()->forget(self::SESSION_KEY);

        $googleUser = Socialite::driver('google')->user();

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user && $user->role !== $intendedRole) {
            return redirect()
                ->route("filament.{$intendedRole->panelId()}.auth.login")
                ->withErrors(['email' => 'এই ইমেইলটি অন্য একটি রোলের সাথে যুক্ত।']);
        }

        $user = $user
            ? tap($user)->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ])
            : $this->createUser($googleUser, $intendedRole);

        auth()->login($user);

        return redirect()->to(Filament::getPanel($intendedRole->panelId())->getUrl());
    }

    private function createUser(\Laravel\Socialite\Contracts\User $googleUser, UserRole $role): User
    {
        $user = User::create([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'role' => $role,
            'status' => $role === UserRole::Staff ? UserStatus::Pending : UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function googleOnlyRoleOrAbort(?string $role): UserRole
    {
        $role = UserRole::tryFrom((string) $role);

        abort_if($role === null || $role->isPasswordAuthenticated(), 404);

        return $role;
    }
}
