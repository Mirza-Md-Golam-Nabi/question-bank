<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;

/**
 * The "which user does this belong to" field of the Admin's billing forms
 * (payments, subscriptions): a searchable select over the record's `user`
 * relationship.
 */
class UserSelect
{
    public static function make(): Select
    {
        return Select::make('user_id')
            ->label(__('User'))
            ->relationship('user', 'name')
            ->searchable()
            ->preload()
            ->required();
    }
}
