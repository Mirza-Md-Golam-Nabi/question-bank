<x-filament-panels::page>
    @php
        $gradients = [
            'from-emerald-400 to-teal-600',
            'from-sky-400 to-blue-600',
            'from-lime-400 to-green-600',
            'from-fuchsia-400 to-purple-600',
            'from-amber-400 to-orange-500',
            'from-rose-400 to-pink-600',
        ];
        $breakdown = $this->breakdown();
    @endphp

    @if ($breakdown->isEmpty())
        <x-filament::section>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No approved questions yet.') }}</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($breakdown as $index => $row)
                @php $gradient = $gradients[$index % count($gradients)]; @endphp

                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $gradient }} p-4 text-white shadow-sm">
                    <x-filament::icon icon="heroicon-o-check-badge" class="absolute -right-2 -top-2 h-16 w-16 text-white/15" />

                    <span class="relative block text-xs font-medium text-white/80 lg:text-sm">{{ $row['class'] }}</span>
                    <p class="relative mt-0.5 text-sm font-bold lg:text-base">{{ $row['subject'] }}</p>
                    <p class="relative mt-2 text-2xl font-extrabold lg:text-3xl">{{ $row['count'] }}</p>
                    <span class="relative block text-xs text-white/80">{{ trans_choice('question approved|questions approved', $row['count']) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
