@php
    use App\Filament\Support\CardPalette;

    $breakdown = $this->breakdown();
@endphp

{{-- Shared by the Staff "Approved questions" and "Pending questions" pages. --}}
<x-filament-panels::page>
    @if ($breakdown->isEmpty())
        <x-filament::section>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ $this->emptyText() }}</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($breakdown as $index => $row)
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ CardPalette::gradient($index, $this->paletteOffset()) }} p-4 text-white shadow-sm">
                    <x-filament::icon :icon="$this->cardIcon()" class="absolute -right-2 -top-2 h-16 w-16 text-white/15" />

                    <span class="relative block text-xs font-medium text-white/80 lg:text-sm">{{ $row['class'] }}</span>
                    <p class="relative mt-0.5 text-sm font-bold lg:text-base">{{ $row['subject'] }}</p>
                    <p class="relative mt-2 text-2xl font-extrabold lg:text-3xl">{{ $row['count'] }}</p>
                    <span class="relative block text-xs text-white/80">{{ $this->countLabel($row['count']) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
