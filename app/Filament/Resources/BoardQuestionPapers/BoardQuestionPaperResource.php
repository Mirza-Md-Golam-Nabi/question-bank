<?php

namespace App\Filament\Resources\BoardQuestionPapers;

use App\Filament\Resources\BoardQuestionPapers\Pages\CreateBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers;
use App\Filament\Support\Resources\BoardQuestionPaperResourceBase;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Admin's past papers: everyone's, with approve and reject.
 */
class BoardQuestionPaperResource extends BoardQuestionPaperResourceBase
{
    protected static bool $reviewsPapers = true;

    public static function getEloquentQuery(): Builder
    {
        // The Admin reviews every paper, whoever entered it.
        return parent::getEloquentQuery();
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
