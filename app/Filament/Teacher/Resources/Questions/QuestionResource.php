<?php

namespace App\Filament\Teacher\Resources\Questions;

use App\Filament\Support\Resources\QuestionResourceBase;
use App\Filament\Teacher\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Teacher\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Teacher\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Teacher\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions;
use Illuminate\Database\Eloquent\Builder;

class QuestionResource extends QuestionResourceBase
{
    protected static ?string $navigationLabel = 'My questions';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // "My questions" is the teacher's own work only. Other people's
        // approved questions are browsed and picked on the Select
        // questions page, which reads the approved pool directly — so
        // nothing here ever needs to reach beyond the owner (CLAUDE.md rule 2).
        return parent::getEloquentQuery()->ownedBy(auth()->user());
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
