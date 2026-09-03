<?php

namespace App\Filament\Student\Pages\Auth;

use App\Enums\UserRole;
use App\Filament\Support\Auth\GoogleOnlyLogin;

class Login extends GoogleOnlyLogin
{
    public static function role(): UserRole
    {
        return UserRole::Student;
    }
}
