<?php

namespace App\Filament\Staff\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\StaffPayout;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Drill-down reached by clicking the "Total Paid" card on MyEarnings — a
 * plain list of this staff member's payout batches (which month, how much,
 * what the admin's reference note said). Not a nav item itself.
 */
class PayoutHistory extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.staff.pages.payout-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Payout History');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MyEarnings::getUrl(panel: 'staff') => __('My Earnings'),
            __('Payout History'),
        ];
    }

    /**
     * @return Collection<int, StaffPayout>
     */
    public function payouts(): Collection
    {
        return StaffPayout::where('staff_id', Auth::id())
            ->with('paidBy')
            ->latest('paid_at')
            ->get();
    }
}
