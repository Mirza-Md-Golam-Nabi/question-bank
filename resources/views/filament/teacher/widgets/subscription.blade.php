@php
    $subscription = $this->subscription();
    $limit = $this->monthlyLimit();
    $used = $this->usedThisMonth();
    $reachedLimit = $limit !== null && $used >= $limit;
@endphp

<x-filament::section>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            @if ($subscription)
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('Current plan') }}: {{ $subscription->plan->name }}
                </p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Usage this month') }}:
                    <span @class(['font-semibold', 'text-danger-600 dark:text-danger-400' => $reachedLimit])>
                        {{ $used }} / {{ $limit ?? __('Unlimited') }}
                    </span>
                </p>

                @if ($reachedLimit)
                    <p class="mt-1 text-sm font-medium text-danger-600 dark:text-danger-400">
                        {{ __('The free limit for this month is used up — upgrade your plan to publish new exams.') }}
                    </p>
                @endif
            @else
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('No active subscription') }}
                </p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Subscribe to a plan to create or publish exams.') }}
                </p>
            @endif
        </div>

        <x-filament::button tag="a" href="{{ $this->mySubscriptionUrl() }}" wire:navigate color="gray" icon="heroicon-o-identification">
            {{ __('My Subscription') }}
        </x-filament::button>
    </div>
</x-filament::section>
