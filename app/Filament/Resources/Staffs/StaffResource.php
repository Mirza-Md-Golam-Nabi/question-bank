<?php

namespace App\Filament\Resources\Staffs;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Staffs\Pages\ManageStaffs;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\StaffLikeUserResourceSchema;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Staffs';

    protected static ?string $slug = 'staffs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::People;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Staff);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(StaffLikeUserResourceSchema::formComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...StaffLikeUserResourceSchema::tableColumns(),
                TextColumn::make('staffProfile.total_questions_approved')->label(__('Approved')),
                TextColumn::make('staffProfile.total_earned')->label(__('Earned'))->money('BDT'),
                TextColumn::make('staffProfile.total_paid')->label(__('Paid'))->money('BDT'),
            ])
            ->filters(StaffLikeUserResourceSchema::tableFilters())
            ->recordActions([
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
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaffs::route('/'),
        ];
    }
}
