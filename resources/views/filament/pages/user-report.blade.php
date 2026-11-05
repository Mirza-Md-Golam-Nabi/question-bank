@php
    $totals = $this->totals();
@endphp

<x-filament-panels::page>
    <div class="qb-compact-tabs">
        <x-filament::tabs>
            @foreach ($this->tabs() as $key => $label)
                <x-filament::tabs.item
                    :active="$tab === $key"
                    :badge="$totals[$key]['total']"
                    wire:click="selectTab('{{ $key }}')"
                    wire:key="user-report-tab-{{ $key }}"
                >
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    <div class="qb-compact-stats">
        @livewire(\App\Filament\Widgets\UserReportStatsWidget::class, ['figures' => $this->figures()])
    </div>

    {{ $this->table }}
</x-filament-panels::page>
