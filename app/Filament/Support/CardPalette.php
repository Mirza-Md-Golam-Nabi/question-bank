<?php

namespace App\Filament\Support;

/**
 * The colours the app's card grids cycle through (the content browser
 * cards, the Staff question breakdown cards), so every grid draws from the
 * same palette. Written as whole class names, because Tailwind only
 * generates classes it can find as literal text.
 */
class CardPalette
{
    private const GRADIENTS = [
        'from-amber-400 to-orange-500',
        'from-sky-400 to-blue-600',
        'from-emerald-400 to-teal-600',
        'from-fuchsia-400 to-purple-600',
        'from-rose-400 to-pink-600',
        'from-lime-400 to-green-600',
    ];

    /**
     * The gradient of the card at `$index`; `$offset` starts a grid on a
     * different colour so neighbouring pages don't look identical.
     */
    public static function gradient(int $index, int $offset = 0): string
    {
        return self::GRADIENTS[($index + $offset) % count(self::GRADIENTS)];
    }
}
