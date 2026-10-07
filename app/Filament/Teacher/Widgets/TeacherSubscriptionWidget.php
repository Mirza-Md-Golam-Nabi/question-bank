<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\ExamType;
use App\Filament\Teacher\Pages\MySubscription;
use App\Models\Exam;
use App\Models\Subscription;
use App\Services\SubscriptionLimitService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherSubscriptionWidget extends Widget
{
    protected string $view = 'filament.teacher.widgets.subscription';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function subscription(): ?Subscription
    {
        return app(SubscriptionLimitService::class)->activeSubscriptionFor(Auth::user());
    }

    public function monthlyLimit(): ?int
    {
        return app(SubscriptionLimitService::class)->monthlyExamLimitFor(Auth::user());
    }

    public function usedThisMonth(): int
    {
        return Exam::createdThisMonthBy(Auth::user(), ExamType::TeacherExam)->count();
    }

    public function mySubscriptionUrl(): string
    {
        return MySubscription::getUrl(panel: 'teacher');
    }
}
