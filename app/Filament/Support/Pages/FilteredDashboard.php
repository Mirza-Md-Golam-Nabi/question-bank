<?php

namespace App\Filament\Support\Pages;

use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Livewire;
use Filament\Widgets\WidgetConfiguration;

/**
 * A dashboard with filters of its own (the performance analytics' — see
 * the Student and Teacher dashboards). Filament hands the filters to every
 * widget on it; a widget that doesn't read them gets them as an HTML
 * attribute, and its loading placeholder fails on the array. So here only
 * a widget that reads them (InteractsWithPageFilters) is given them.
 */
abstract class FilteredDashboard extends Dashboard
{
    use HasFiltersForm;

    /**
     * Filament's own, except for when the filters are passed on.
     *
     * @param  array<string|WidgetConfiguration>  $widgets
     * @param  array<string, mixed>  $data
     * @return array<Livewire>
     */
    public function getWidgetsSchemaComponents(array $widgets, array $data = []): array
    {
        return collect($widgets)
            ->values()
            ->filter(fn (string|WidgetConfiguration $widget): bool => $this->normalizeWidgetClass($widget)::canView())
            ->map(fn (string|WidgetConfiguration $widget, int $widgetKey): Livewire => Livewire::make(
                $widgetClass = $this->normalizeWidgetClass($widget),
                fn (): array => [
                    ...$this->getWidgetData(),
                    ...$data,
                    ...(($widget instanceof WidgetConfiguration) ? [
                        ...$widget->widget::getDefaultProperties(),
                        ...$widget->getProperties(),
                    ] : $widget::getDefaultProperties()),
                    ...(property_exists($widgetClass, 'pageFilters') ? ['pageFilters' => $this->filters] : []),
                ],
            )->key("{$widgetClass}-{$widgetKey}")->liberatedFromContainerGrid())
            ->all();
    }
}
