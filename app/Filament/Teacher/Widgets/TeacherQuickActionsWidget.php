<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherQuickActionsWidget extends Widget
{
    protected string $view = 'filament.teacher.widgets.quick-actions';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function canAddQuestion(): bool
    {
        return Auth::user()?->can('create', Question::class) ?? false;
    }

    public function addQuestionUrl(): string
    {
        return QuestionResource::getUrl('index', panel: 'teacher');
    }

    public function createExamUrl(): string
    {
        return ExamResource::getUrl('create', panel: 'teacher');
    }
}
