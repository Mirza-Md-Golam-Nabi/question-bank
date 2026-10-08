<?php

namespace App\Filament\Teacher\Resources\Questions;

use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Teacher\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions;
use App\Filament\Teacher\Resources\Questions\Schemas\QuestionForm;
use App\Filament\Teacher\Resources\Questions\Tables\QuestionsTable;
use App\Models\Question;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Question::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $navigationLabel = 'My questions';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return QuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestions::route('/'),
            'create' => CreateQuestion::route('/create'),
            'edit' => EditQuestion::route('/{record}/edit'),
        ];
    }
}
