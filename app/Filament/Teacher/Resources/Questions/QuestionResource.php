<?php

namespace App\Filament\Teacher\Resources\Questions;

use App\Filament\Support\Resources\QuestionResourceBase;
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
        return parent::getEloquentQuery()->visibleTo(auth()->user());
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
