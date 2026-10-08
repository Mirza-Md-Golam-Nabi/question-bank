<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Pages\Questions\CreateQuestionPage;

class CreateQuestion extends CreateQuestionPage
{
    protected static string $resource = QuestionResource::class;
}
