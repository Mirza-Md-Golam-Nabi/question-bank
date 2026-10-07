<x-filament-panels::page>
    <form wire:submit="join" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('Join exam') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
