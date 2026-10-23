<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * A School's details — the same fields wherever they are filled in: by the
 * School while it waits for approval, and by the Admin who reviews it.
 */
class SchoolDetailsForm
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label(__('School name'))
                ->required()
                ->maxLength(255),
            TextInput::make('eiin')
                ->label(__('EIIN'))
                ->maxLength(20),
            TextInput::make('address')
                ->label(__('Address'))
                ->maxLength(255),
            TextInput::make('phone')
                ->label(__('Phone'))
                ->tel()
                ->maxLength(20),
        ];
    }
}
