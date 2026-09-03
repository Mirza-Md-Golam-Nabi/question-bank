<?php

namespace App\Filament\Support\Auth;

use App\Enums\UserRole;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Shared "Continue with Google" login page for the Teacher/Staff/Student
 * panels — no password form, no registration. Each panel's own Login
 * subclass only needs to declare which role it authenticates.
 */
abstract class GoogleOnlyLogin extends Login
{
    abstract public static function role(): UserRole;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Actions::make([
                Action::make('continueWithGoogle')
                    ->label(__('Continue with Google'))
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->url(route('auth.google.redirect', ['role' => static::role()->value]))
                    ->color('primary'),
            ])->fullWidth(),
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        return __('Sign in to :panel', ['panel' => static::role()->getLabel()]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }
}
