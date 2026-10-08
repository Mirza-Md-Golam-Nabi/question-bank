@props(['icon'])

{{-- One "icon + count" line under a browser card's title. --}}
<span class="inline-flex items-center gap-1">
    <x-filament::icon :icon="$icon" class="h-3.5 w-3.5 shrink-0 lg:h-4 lg:w-4" />
    {{ $slot }}
</span>
