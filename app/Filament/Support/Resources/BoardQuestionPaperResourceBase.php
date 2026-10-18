<?php

namespace App\Filament\Support\Resources;

use App\Filament\Resources\BoardQuestionPapers\Tables\BoardQuestionPapersTable;
use App\Filament\Support\BoardQuestionPaperFormSchema;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\RelationManagers\PaperCqQuestionsRelationManager;
use App\Filament\Support\RelationManagers\PaperMcqQuestionsRelationManager;
use App\Models\BoardQuestionPaper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * What the Admin's and a Staff member's past papers have in common: the
 * model, the details form, the MCQ and CQ lists on a paper's page, and the
 * table. A panel's own resource states its pages and — never shared — its
 * `getEloquentQuery()`, which decides whose papers it lists.
 */
abstract class BoardQuestionPaperResourceBase extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = BoardQuestionPaper::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $modelLabel = 'past paper';

    protected static ?string $navigationLabel = 'Past papers';

    /**
     * Whether this panel approves and rejects papers (the Admin's) or only
     * writes them (a Staff member's).
     */
    protected static bool $reviewsPapers = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(BoardQuestionPaperFormSchema::components());
    }

    public static function table(Table $table): Table
    {
        return BoardQuestionPapersTable::configure($table, static::$reviewsPapers);
    }

    public static function getRelations(): array
    {
        return [
            PaperMcqQuestionsRelationManager::class,
            PaperCqQuestionsRelationManager::class,
        ];
    }
}
