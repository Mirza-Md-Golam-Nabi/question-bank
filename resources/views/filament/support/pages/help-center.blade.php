<x-filament-panels::page>
    @if ($guide = $this->guide)
        <div class="space-y-4">
            <x-filament::link tag="button" wire:click="back" icon="heroicon-m-arrow-left" color="gray">
                {{ __('All guides') }}
            </x-filament::link>

            <x-filament::section :heading="$guide->title()" :description="$guide->category->getLabel()">
                <x-guides.content :guide="$guide" />
            </x-filament::section>
        </div>
    @else
        <div class="space-y-6">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__('Search the guides')" />
            </x-filament::input.wrapper>

            @if ($page = $this->narrowedToPage())
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <span>{{ __('Guides for: :page', ['page' => $page->getLabel()]) }}</span>
                    <x-filament::link tag="button" wire:click="showAll" size="sm">{{ __('Show all') }}</x-filament::link>
                </div>
            @endif

            @forelse ($this->sections as $section)
                <x-filament::section :heading="$section['category']->getLabel()">
                    <ul class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($section['guides'] as $listed)
                            <li>
                                <button type="button" wire:click="open({{ $listed->id }})" class="flex w-full items-center justify-between gap-3 py-3 text-start text-sm font-medium text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                    <span>{{ $listed->title() }}</span>
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 shrink-0 text-gray-400" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ filled($this->search) ? __('No guide matches your search.') : __('No guides yet.') }}
                    </p>
                </x-filament::section>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
