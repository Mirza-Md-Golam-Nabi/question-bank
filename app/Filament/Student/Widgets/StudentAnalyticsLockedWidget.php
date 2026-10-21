<?php

namespace App\Filament\Student\Widgets;

use App\Filament\Student\Pages\MySubscription;
use App\Filament\Support\Widgets\Performance\AnalyticsLockedWidget;

class StudentAnalyticsLockedWidget extends AnalyticsLockedWidget
{
    protected static ?int $sort = 1;

    public function subscriptionUrl(): string
    {
        return MySubscription::getUrl(panel: 'student');
    }

    public function pitch(): string
    {
        return __('See how your scores move, which subjects and difficulties hold you back, and how much of each subject you have covered — with a paid plan that includes analytics.');
    }
}
