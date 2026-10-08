<?php

namespace App\Filament\Resources\Subjects;

use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\TableActions;
use App\Models\Subject;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubjectResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name (English)'))
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name_bn')
                    ->label(__('Name (Bangla)'))
                    ->unique(ignoreRecord: true),
                TextInput::make('short_name')
                    ->label(__('Short name'))
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name (English)'))->searchable()->sortable(),
                TextColumn::make('name_bn')->label(__('Name (Bangla)'))->searchable()->sortable(),
                TextColumn::make('short_name')->label(__('Short name'))->badge()->searchable()->sortable(),
                TextColumn::make('class_subjects_count')->label(__('Classes'))->counts('classSubjects'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions(TableActions::editAndDelete(iconButtons: true))
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubjects::route('/'),
        ];
    }
}
