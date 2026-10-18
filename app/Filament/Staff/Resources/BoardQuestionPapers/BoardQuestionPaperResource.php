<?php

namespace App\Filament\Staff\Resources\BoardQuestionPapers;

use App\Filament\Staff\Resources\BoardQuestionPapers\Pages\CreateBoardQuestionPaper;
use App\Filament\Staff\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Staff\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers;
use App\Filament\Support\Resources\BoardQuestionPaperResourceBase;
use Illuminate\Database\Eloquent\Builder;

/**
 * A Staff member's own past papers: entered here, reviewed by an Admin, and
 * paid per approved paper.
 */
class BoardQuestionPaperResource extends BoardQuestionPaperResourceBase
{
    public static function getEloquentQuery(): Builder
    {
        // Only their own — no one else's papers, approved or not.
        return parent::getEloquentQuery()->ownedBy(auth()->user());
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
