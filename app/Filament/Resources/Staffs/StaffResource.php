<?php

namespace App\Filament\Resources\Staffs;

use App\Enums\UserRole;
use App\Filament\Resources\Staffs\Pages\ManageStaffs;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\StaffLikeUserResourceSchema;
use App\Filament\Support\TableActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
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
                ...StaffLikeUserResourceSchema::statusActions(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaffs::route('/'),
        ];
    }
}
