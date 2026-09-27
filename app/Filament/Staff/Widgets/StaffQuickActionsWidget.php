<?php

namespace App\Filament\Staff\Widgets;

use App\Filament\Staff\Resources\Questions\QuestionResource;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StaffQuickActionsWidget extends Widget
{
    protected string $view = 'filament.staff.widgets.quick-actions';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function isStaffSuspended(): bool
    {
        return Auth::user()?->isSuspended() ?? false;
    }

    public function addQuestionUrl(): string
    {
        return QuestionResource::getUrl('index', panel: 'staff');
    }
}
