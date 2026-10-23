<?php

namespace App\Filament\Resources\Schools;

use App\Enums\UserRole;
use App\Filament\Resources\Schools\Pages\ManageSchools;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\SchoolDetailsForm;
use App\Filament\Support\StaffLikeUserResourceSchema;
use App\Filament\Support\TableActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The School accounts: an Admin checks the details a new one gave (name,
 * EIIN, address, phone) and approves it to speak for that school — or
 * suspends it. Like Staff, the accounts themselves come from Google
 * sign-in only.
 */
class SchoolResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'school';

    protected static ?string $navigationLabel = 'Schools';

    protected static ?string $slug = 'schools';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::People;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::School)->with('schoolProfile');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...StaffLikeUserResourceSchema::formComponents(),
            Section::make(__('School'))
                ->relationship('schoolProfile')
                ->schema(SchoolDetailsForm::components())
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...StaffLikeUserResourceSchema::tableColumns(),
                TextColumn::make('schoolProfile.name')->label(__('School name'))->searchable()->placeholder('—'),
                TextColumn::make('schoolProfile.eiin')->label(__('EIIN'))->searchable()->placeholder('—'),
                TextColumn::make('schoolProfile.phone')->label(__('Phone'))->placeholder('—'),
                IconColumn::make('school_connected')
                    ->label(__('Software connected'))
                    ->state(fn (User $record): bool => (bool) $record->schoolProfile?->isConnected())
                    ->boolean()
                    ->tooltip(fn (User $record): ?string => $record->schoolProfile?->installation_url),
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
            'index' => ManageSchools::route('/'),
        ];
    }
}
