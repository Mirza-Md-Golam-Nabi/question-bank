@props([
    'index' => 0,
    'paletteOffset' => 0,
    'title',
    'icon',
    'href' => null,
    'menu' => null,
])

@php
    /**
     * One card of the Class → Subject → Chapter → Topic browser grids. The
     * whole card is a link when `href` is given; the `menu` slot (dropdown
     * items) adds the "⋮" menu for panels that manage the content.
     *
     * Cards cycle through the app's shared palette; `paletteOffset` just
     * starts each level of the browser on a different colour.
     */
    $gradient = \App\Filament\Support\CardPalette::gradient($index, $paletteOffset);
@endphp

<div class="group relative flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-white/10 dark:bg-gray-900">
    @if ($href)
        <a href="{{ $href }}" wire:navigate class="absolute inset-0 z-0" aria-label="{{ $title }}"></a>
    @endif

    <div @class(['relative z-10', 'pointer-events-none' => $href])>
        <div class="h-12 rounded-t-2xl bg-gradient-to-br {{ $gradient }} lg:h-16"></div>

        <div class="flex flex-col gap-2 p-3 lg:gap-3 lg:p-4">
            <div class="-mt-9 flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br {{ $gradient }} text-white shadow-lg ring-4 ring-white dark:ring-gray-900 lg:-mt-12 lg:h-14 lg:w-14 lg:rounded-2xl">
                <x-filament::icon :icon="$icon" class="h-5 w-5 lg:h-7 lg:w-7" />
            </div>

            <div>
                <h3 class="truncate text-xs font-semibold text-gray-950 dark:text-white lg:text-sm">{{ $title }}</h3>
                <div class="mt-1 flex flex-col gap-0.5 text-xs text-gray-500 dark:text-gray-400 lg:text-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    @if ($menu)
        <div class="absolute right-1.5 top-1.5 z-20 lg:right-2 lg:top-2">
            <x-filament::dropdown placement="bottom-end">
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-white/80 text-gray-600 opacity-100 backdrop-blur transition hover:bg-white md:opacity-0 md:group-hover:opacity-100 dark:bg-gray-900/80 dark:text-gray-300 dark:hover:bg-gray-900 lg:h-8 lg:w-8"
                    >
                        <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-4 w-4" />
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    {{ $menu }}
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
    @endif
</div>
