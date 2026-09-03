<x-filament-panels::page>
    <form wire:submit="generate" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            Generate exam
        </x-filament::button>
    </form>
</x-filament-panels::page>
