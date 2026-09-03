<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Extends Filament's default password login only to pre-fill the Super
 * Admin credentials in local development, so switching between panels
 * during testing doesn't require re-typing them each time.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (app()->isLocal()) {
            $this->form->fill([
                'email' => 'superadmin@example.com',
                'password' => 'password',
            ]);
        }
    }
}
