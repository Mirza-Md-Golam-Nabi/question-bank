<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Pages\Questions\ListQuestionsByChapterPage;

class ListQuestions extends ListQuestionsByChapterPage
{
    protected static string $resource = QuestionResource::class;
}
