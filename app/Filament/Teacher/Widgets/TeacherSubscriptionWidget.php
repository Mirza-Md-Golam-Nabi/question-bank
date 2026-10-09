<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\ExamType;
use App\Filament\Support\Concerns\ShowsPlanSummary;
use App\Filament\Teacher\Pages\MySubscription;
use App\Models\Exam;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherSubscriptionWidget extends Widget
{
    use ShowsPlanSummary;

    protected string $view = 'filament.teacher.widgets.subscription';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function usedThisMonth(): int
    {
        return Exam::createdThisMonthBy(Auth::user(), ExamType::TeacherExam)->count();
    }

    public function mySubscriptionUrl(): string
    {
        return MySubscription::getUrl(panel: 'teacher');
    }
}
