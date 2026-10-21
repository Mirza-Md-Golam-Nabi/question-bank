<?php

namespace App\Filament\Student\Pages;

use App\Enums\PerformanceSource;
use App\Filament\Support\Pages\FilteredDashboard;
use App\Services\SubscriptionLimitService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * The student's dashboard, with which kind of exam their performance
 * analytics are worked out from — every kind together by default.
 */
class Dashboard extends FilteredDashboard
{
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source')
                ->label(__('Analytics from'))
                ->options(PerformanceSource::class)
                ->default(PerformanceSource::All->value)
                ->selectablePlaceholder(false)
                ->visible(fn (): bool => app(SubscriptionLimitService::class)->allowsAnalytics(Auth::user())),
        ]);
    }
}
