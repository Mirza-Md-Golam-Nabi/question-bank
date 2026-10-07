<?php

namespace App\Filament\Staff\Resources\Questions;

use App\Filament\Staff\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Staff\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Staff\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Staff\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Staff\Resources\Questions\Pages\EditQuestion;
use App\Filament\Staff\Resources\Questions\Pages\ListQuestions;
use App\Filament\Staff\Resources\Questions\Schemas\QuestionForm;
use App\Filament\Staff\Resources\Questions\Tables\QuestionsTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
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

    public static function getEloquentQuery(): Builder
    {
        // Staff only ever needs their own contributions — no approved pool
        // to browse (CLAUDE.md rule 2).
        return parent::getEloquentQuery()->where('created_by', auth()->id());
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
            'index' => BrowseClasses::route('/'),
            'subjects' => BrowseSubjects::route('/classes/{class}/subjects'),
            'chapters' => BrowseChapters::route('/classes/{class}/subjects/{classSubject}/chapters'),
            'list' => ListQuestions::route('/chapters/{chapter}/questions'),
            'create' => CreateQuestion::route('/create'),
            'edit' => EditQuestion::route('/{record}/edit'),
        ];
    }
}
