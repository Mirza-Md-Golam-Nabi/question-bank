<?php

namespace App\Filament\Staff\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PendingApprovalNotice extends Page
{
    protected static ?string $slug = 'pending-approval';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.staff.pages.pending-approval-notice';

    public function getTitle(): string|Htmlable
    {
        return __('Pending Approval');
    }
}
