<x-filament::section :heading="__('My plans')">
    <x-slot name="afterHeader">
        <x-filament::button tag="a" href="{{ $this->mySubscriptionUrl() }}" wire:navigate color="gray" size="sm" icon="heroicon-o-identification">
            {{ __('My Subscription') }}
        </x-filament::button>
    </x-slot>

    <x-plan-usage :summary="$this->planSummary()" />
</x-filament::section>
