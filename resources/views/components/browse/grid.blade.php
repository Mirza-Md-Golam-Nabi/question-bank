@props([
    'backUrl' => null,
    'backLabel' => null,
    'isEmpty' => false,
    'emptyText' => null,
])

{{-- The card grid of a browser page: optional "back" link, the cards (slot),
     or the empty message when there is nothing to show. --}}
<div>
    @if ($backUrl)
        <a
            href="{{ $backUrl }}"
            wire:navigate
            class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 lg:mb-4 lg:text-sm"
        >
            <x-filament::icon icon="heroicon-m-arrow-left" class="h-3.5 w-3.5 lg:h-4 lg:w-4" />
            {{ $backLabel }}
        </a>
    @endif

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4">
        @if ($isEmpty)
            <div class="col-span-full">
                <x-filament::section>
                    <p class="text-center text-xs text-gray-500 dark:text-gray-400 lg:text-sm">{{ $emptyText }}</p>
                </x-filament::section>
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
