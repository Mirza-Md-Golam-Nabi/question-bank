<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;

/**
 * Links to a panel's own routes from pages several panels share (a
 * Teacher's and a School's exam pages): the route of the panel the page is
 * open in, never a fixed one.
 */
class CurrentPanel
{
    public static function id(): string
    {
        return (string) Filament::getCurrentPanel()?->getId();
    }

    /**
     * @param  mixed  $parameters  The route's parameters, as route() takes them.
     */
    public static function route(string $name, mixed $parameters = []): string
    {
        return route('filament.'.self::id().'.'.$name, $parameters);
    }
}
