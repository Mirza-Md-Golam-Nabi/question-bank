@props([
    'backUrl' => null,
    'backLabel' => null,
    'isEmpty' => false,
    'emptyText' => null,
    'searchable' => false,
])

{{-- The card grid of a browser page: optional "back" link, the cards (slot),
     or the empty message when there is nothing to show.

     `searchable` adds a search box that filters the cards in the browser —
     every card of the page is already here, so nothing is asked of the
     server. A card is matched on its `search` text (each card's names);
     several words must all match; case, Bangla/ASCII digits and how a
     Bangla letter was typed do not matter. --}}
<div
    x-data="{
        query: '',
        normalize(text) {
            return (text ?? '')
                .normalize('NFC')
                .toLowerCase()
                .replace(/[০-৯]/g, (digit) => String('০১২৩৪৫৬৭৮৯'.indexOf(digit)))
                .replace(/\s+/g, ' ')
                .trim()
        },
        matches(text) {
            const words = this.normalize(this.query).split(' ').filter(Boolean)
            const haystack = this.normalize(text)

            return words.every((word) => haystack.includes(word))
        },
        get nothingMatches() {
            return this.query.trim() !== ''
                && ! [...this.$root.querySelectorAll('[data-browse-search]')].some((card) => this.matches(card.dataset.browseSearch))
        },
    }"
>
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

    @if ($searchable && ! $isEmpty)
        <div class="mb-3 max-w-md lg:mb-4">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    x-model="query"
                    x-on:keydown.escape="query = ''"
                    :placeholder="__('Search')"
                    autocomplete="off"
                />
            </x-filament::input.wrapper>
        </div>
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

            <div x-cloak x-show="nothingMatches" class="col-span-full">
                <x-filament::section>
                    <p class="text-center text-xs text-gray-500 dark:text-gray-400 lg:text-sm">{{ __('Nothing matches your search.') }}</p>
                </x-filament::section>
            </div>
        @endif
    </div>
</div>
