<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The one page an account waiting for an Admin's approval can open
 * (EnsureAccountIsApproved sends it here): a Staff member's, a School's.
 * A panel's own page says what it shows.
 */
abstract class PendingApprovalPage extends Page
{
    use TranslatesPageLabels;

    protected static ?string $slug = 'pending-approval';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string|Htmlable
    {
        return __('Pending Approval');
    }
}
