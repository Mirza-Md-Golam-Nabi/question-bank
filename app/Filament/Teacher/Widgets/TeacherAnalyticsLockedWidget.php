<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Support\Widgets\Performance\AnalyticsLockedWidget;
use App\Filament\Teacher\Pages\MySubscription;

class TeacherAnalyticsLockedWidget extends AnalyticsLockedWidget
{
    protected static ?int $sort = 6;

    public function subscriptionUrl(): string
    {
        return MySubscription::getUrl(panel: 'teacher');
    }

    public function pitch(): string
    {
        return __("See how your class's scores move, which chapters it is weak in, each student's progress, and who needs your attention — with a paid plan that includes analytics.");
    }
}
