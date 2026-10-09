<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Support\Widgets\SuspensionNoticeWidget;
use App\Filament\Teacher\Pages\RequestReactivation;

class TeacherSuspensionNoticeWidget extends SuspensionNoticeWidget
{
    protected function reactivationPage(): string
    {
        return RequestReactivation::class;
    }
}
