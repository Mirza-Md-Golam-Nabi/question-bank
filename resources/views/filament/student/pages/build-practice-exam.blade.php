<x-filament-panels::page>
    <form wire:submit="build" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            Build exam
        </x-filament::button>
    </form>
</x-filament-panels::page>
