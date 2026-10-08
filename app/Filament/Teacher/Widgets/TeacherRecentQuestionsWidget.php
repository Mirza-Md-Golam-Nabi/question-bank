<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Support\Widgets\RecentQuestionsWidget;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion;
use App\Models\Question;

class TeacherRecentQuestionsWidget extends RecentQuestionsWidget
{
    protected static ?int $sort = 4;

    protected function editQuestionUrl(Question $question): string
    {
        return EditQuestion::getUrl(['record' => $question], panel: 'teacher');
    }
}
