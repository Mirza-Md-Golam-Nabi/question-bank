<?php

namespace App\Filament\Staff\Widgets;

use App\Filament\Staff\Pages\RequestReactivation;
use App\Filament\Support\Widgets\SuspensionNoticeWidget;

class StaffSuspensionNoticeWidget extends SuspensionNoticeWidget
{
    protected function reactivationPage(): string
    {
        return RequestReactivation::class;
    }
}
