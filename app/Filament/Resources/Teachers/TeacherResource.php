<?php

namespace App\Filament\Resources\Teachers;

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\StaffLikeUserResourceSchema;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeacherResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Teachers';

    protected static ?string $slug = 'teachers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::People;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Teacher);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(StaffLikeUserResourceSchema::formComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(StaffLikeUserResourceSchema::tableColumns())
            ->filters(StaffLikeUserResourceSchema::tableFilters())
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ManageTeachers::route('/'),
        ];
    }
}
