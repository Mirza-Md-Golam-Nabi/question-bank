<?php

namespace App\Filament\Staff\Resources\Questions\Pages;

use App\Filament\Staff\Resources\Questions\QuestionResource;
use App\Filament\Support\Pages\Questions\ListQuestionsByChapterPage;

class ListQuestions extends ListQuestionsByChapterPage
{
    protected static string $resource = QuestionResource::class;
}
