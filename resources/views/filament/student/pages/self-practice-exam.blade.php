{{-- Shared by both self-practice pages (Auto-Generate and Manual Selection). --}}
<x-filament-panels::page>
    <form wire:submit="start" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ $this->submitLabel() }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
