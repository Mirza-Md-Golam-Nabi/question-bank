<?php

namespace App\Filament\Resources\Questions;

use App\Filament\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Resources\Questions\Pages\BrowseTopics;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Filament\Support\Resources\QuestionResourceBase;
use Illuminate\Database\Eloquent\Builder;

class QuestionResource extends QuestionResourceBase
{
    public static function getEloquentQuery(): Builder
    {
        // An Admin reviews and manages every question but a School's own,
        // which only that School sees and nobody approves (CLAUDE.md rule 12).
        return parent::getEloquentQuery()->where('is_private', false);
    }

    public static function getPages(): array
    {
        return [
            'index' => BrowseClasses::route('/'),
            'subjects' => BrowseSubjects::route('/classes/{class}/subjects'),
            'chapters' => BrowseChapters::route('/classes/{class}/subjects/{classSubject}/chapters'),
            'topics' => BrowseTopics::route('/classes/{class}/subjects/{classSubject}/chapters/{chapter}/topics'),
            'list' => ListQuestions::route('/chapters/{chapter}/questions'),
            'create' => CreateQuestion::route('/create'),
            'edit' => EditQuestion::route('/{record}/edit'),
        ];
    }
}
