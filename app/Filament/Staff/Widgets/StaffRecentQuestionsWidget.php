<?php

namespace App\Filament\Staff\Widgets;

use App\Filament\Staff\Resources\Questions\Pages\EditQuestion;
use App\Filament\Support\Widgets\RecentQuestionsWidget;
use App\Models\Question;

class StaffRecentQuestionsWidget extends RecentQuestionsWidget
{
    protected static ?int $sort = 5;

    protected function editQuestionUrl(Question $question): string
    {
        return EditQuestion::getUrl(['record' => $question], panel: 'staff');
    }
}
