<x-filament-panels::page>
    @php $subscription = $this->subscription(); @endphp

    <x-filament::section :heading="__('Current plan')">
        @if ($subscription)
            <div class="space-y-2">
                <p class="text-lg font-bold">{{ $subscription->plan->name }}</p>
                <p class="text-sm text-gray-500">
                    {{ __('Status') }}: <span class="font-medium">{{ $subscription->status->getLabel() }}</span>
                </p>
                <p class="text-sm text-gray-500">
                    {{ __('Monthly exam limit') }}:
                    <span class="font-medium">{{ $this->monthlyLimit() ?? __('Unlimited') }}</span>
                </p>
                @if ($subscription->ends_at)
                    <p class="text-sm text-gray-500">{{ __('Renews/expires') }}: {{ $subscription->ends_at->format('M j, Y') }}</p>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __("You don't have an active subscription yet.") }}</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
