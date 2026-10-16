<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Support\Concerns\ShowsPlanSummary;
use App\Filament\Teacher\Pages\MySubscription;
use Filament\Widgets\Widget;

class TeacherSubscriptionWidget extends Widget
{
    use ShowsPlanSummary;

    protected string $view = 'filament.teacher.widgets.subscription';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function mySubscriptionUrl(): string
    {
        return MySubscription::getUrl(panel: 'teacher');
    }
}
