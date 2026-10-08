<?php

namespace App\Filament\Staff\Resources\Questions;

use App\Filament\Staff\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Staff\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Staff\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Staff\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Staff\Resources\Questions\Pages\EditQuestion;
use App\Filament\Staff\Resources\Questions\Pages\ListQuestions;
use App\Filament\Support\Resources\QuestionResourceBase;
use Illuminate\Database\Eloquent\Builder;

class QuestionResource extends QuestionResourceBase
{
    public static function getEloquentQuery(): Builder
    {
        // Staff only ever needs their own contributions — no approved pool
        // to browse (CLAUDE.md rule 2).
        return parent::getEloquentQuery()->where('created_by', auth()->id());
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
