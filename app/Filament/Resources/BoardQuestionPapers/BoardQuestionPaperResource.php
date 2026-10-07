<?php

namespace App\Filament\Resources\BoardQuestionPapers;

use App\Filament\Resources\BoardQuestionPapers\Pages\CreateBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers;
use App\Filament\Resources\BoardQuestionPapers\Schemas\BoardQuestionPaperForm;
use App\Filament\Resources\BoardQuestionPapers\Tables\BoardQuestionPapersTable;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\BoardQuestionPaper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BoardQuestionPaperResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = BoardQuestionPaper::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $navigationLabel = 'Board Question Papers';

    public static function form(Schema $schema): Schema
    {
        return BoardQuestionPaperForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BoardQuestionPapersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoardQuestionPapers::route('/'),
            'create' => CreateBoardQuestionPaper::route('/create'),
            'edit' => EditBoardQuestionPaper::route('/{record}/edit'),
        ];
    }
}
