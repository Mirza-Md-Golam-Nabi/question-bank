<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Student = 'student';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Teacher => 'Teacher',
            self::Staff => 'Staff',
            self::Student => 'Student',
        };
    }

    /**
     * Roles that authenticate with a password via Filament's default login
     * (Admin Panel). Every other role is Google OAuth-only.
     *
     * @return array<self>
     */
    public static function passwordAuthenticated(): array
    {
        return [self::SuperAdmin, self::Admin];
    }

    public function isPasswordAuthenticated(): bool
    {
        return in_array($this, self::passwordAuthenticated(), strict: true);
    }

    /**
     * The Filament panel id this role is allowed into.
     */
    public function panelId(): string
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => 'admin',
            self::Teacher => 'teacher',
            self::Staff => 'staff',
            self::Student => 'student',
        };
    }
}
