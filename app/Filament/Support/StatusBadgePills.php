<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/**
 * Renders a row of small colored pill badges for a Stat's description —
 * used by both StaffStatsOverviewWidget and TeacherStatsOverviewWidget so
 * the "Pending X · Approved Y · Rejected Z" breakdown wraps cleanly instead
 * of as one plain string with stray middle-dots.
 */
class StatusBadgePills
{
    /**
     * @param  array<int, array{label: string, count: int, dotClass: string}>  $badges
     */
    public static function make(array $badges): HtmlString
    {
        $html = array_map(
            fn (array $badge) => <<<HTML
                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium whitespace-nowrap">
                    <span class="h-1.5 w-1.5 rounded-full {$badge['dotClass']}"></span>{$badge['label']} {$badge['count']}
                </span>
                HTML,
            $badges
        );

        return new HtmlString(
            '<span class="mt-1 flex flex-wrap items-center gap-1.5">'.implode('', $html).'</span>'
        );
    }

    public static function questionStatus(int $pending, int $approved, int $rejected): HtmlString
    {
        return self::make([
            ['label' => __('Pending'), 'count' => $pending, 'dotClass' => 'bg-amber-300'],
            ['label' => __('Approved'), 'count' => $approved, 'dotClass' => 'bg-emerald-300'],
            ['label' => __('Rejected'), 'count' => $rejected, 'dotClass' => 'bg-rose-300'],
        ]);
    }
}
