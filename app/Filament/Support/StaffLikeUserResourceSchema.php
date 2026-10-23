<?php

namespace App\Filament\Support;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;

/**
 * Teacher/Staff/School are Google OAuth-only (CLAUDE.md rule 1) — Admin
 * never creates these accounts, only reviews/manages the ones Google
 * sign-in already created. So their resources share this same "view + edit
 * status" form/table instead of each hand-rolling one, and none registers a
 * CreateAction.
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
            TextColumn::make('created_at')->localDateTime()->sortable(),
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

    /**
     * Approve a new account, suspend, suspend for good, or bring it back —
     * for the accounts an Admin approves (Staff, School).
     *
     * @return array<Action>
     */
    public static function statusActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('Approve'))
                ->color('success')
                ->iconButton()
                ->icon('heroicon-o-check-circle')
                ->visible(fn (User $record) => $record->status === UserStatus::Pending)
                ->requiresConfirmation()
                ->action(fn (User $record) => $record->update(['status' => UserStatus::Active])),
            Action::make('suspend')
                ->label(__('Suspend'))
                ->color('warning')
                ->iconButton()
                ->icon('heroicon-o-pause-circle')
                ->visible(fn (User $record) => $record->status === UserStatus::Active)
                ->requiresConfirmation()
                ->action(fn (User $record) => $record->update(['status' => UserStatus::Suspended])),
            Action::make('permanentSuspend')
                ->label(__('Permanent Suspend'))
                ->color('danger')
                ->iconButton()
                ->icon('heroicon-o-no-symbol')
                ->visible(fn (User $record) => $record->status !== UserStatus::PermanentSuspend)
                ->requiresConfirmation()
                ->action(fn (User $record) => $record->update(['status' => UserStatus::PermanentSuspend])),
            Action::make('reactivate')
                ->label(__('Reactivate'))
                ->color('success')
                ->iconButton()
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (User $record) => in_array($record->status, [UserStatus::Suspended, UserStatus::PermanentSuspend], strict: true))
                ->requiresConfirmation()
                ->action(fn (User $record) => $record->update(['status' => UserStatus::Active])),
        ];
    }
}
