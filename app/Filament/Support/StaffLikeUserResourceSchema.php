<?php

namespace App\Filament\Support;

use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;

/**
 * Teacher/Staff are Google OAuth-only (CLAUDE.md rule 1) — Admin never
 * creates these accounts, only reviews/manages the ones Google sign-in
 * already created. So TeacherResource and StaffResource share this same
 * "view + edit status" form/table instead of each hand-rolling one, and
 * neither registers a CreateAction.
 */
class StaffLikeUserResourceSchema
{
    /**
     * @return array<Component>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
            Select::make('status')->options(UserStatus::class)->required(),
        ];
    }

    /**
     * @return array<Column>
     */
    public static function tableColumns(): array
    {
        return [
            ImageColumn::make('avatar')->circular(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ];
    }

    /**
     * @return array<BaseFilter>
     */
    public static function tableFilters(): array
    {
        return [
            SelectFilter::make('status')->options(UserStatus::class),
        ];
    }
}
