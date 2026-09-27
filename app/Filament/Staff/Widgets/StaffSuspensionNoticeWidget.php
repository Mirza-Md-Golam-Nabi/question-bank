<?php

namespace App\Filament\Staff\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StaffSuspensionNoticeWidget extends Widget
{
    protected string $view = 'filament.staff.widgets.suspension-notice';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->isSuspended() ?? false;
    }
}
