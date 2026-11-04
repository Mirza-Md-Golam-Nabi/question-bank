@php($growth = $this->growth())

<x-filament-widgets::widget wire:poll.60s>
    <x-filament::section>
        <div class="mb-4 flex items-center justify-center gap-2 rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
            </span>
            <span class="text-lg font-bold tracking-wide text-red-600 dark:text-red-400">LIVE</span>
            <span class="text-lg font-bold text-gray-950 dark:text-white">{{ __('Database update!') }}</span>
        </div>

        <p class="mx-auto max-w-2xl text-center text-sm text-gray-700 dark:text-gray-300">
            {{ __('New questions join our question bank every day. See below, live, which classes are getting questions now.') }}
        </p>

        <div class="mt-4 flex flex-col items-center gap-1 border-t border-dashed border-gray-300 pt-4 text-center dark:border-white/15">
            @if ($growth['last_added'])
                <p class="flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    <x-filament::icon icon="heroicon-s-check-circle" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    {{ __('New question added :time', ['time' => $growth['last_added']]) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __(':count questions in the last :days days', ['count' => $growth['total'], 'days' => $growth['days']]) }}
                </p>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No question has been added yet.') }}</p>
            @endif
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            {{-- The last days' bars, on a scale of round numbers. --}}
            <div class="rounded-xl border border-gray-200 p-3 sm:p-4 lg:col-span-2 dark:border-white/10" role="img" aria-label="{{ __(':count questions in the last :days days', ['count' => $growth['total'], 'days' => $growth['days']]) }}">
                <div class="mt-5 flex gap-2">
                    <div class="relative h-44 w-9 shrink-0 text-right text-[11px] text-gray-500 sm:h-56 sm:w-10 sm:text-xs dark:text-gray-400">
                        @foreach ($growth['scale'] as $index => $value)
                            <span class="absolute right-0 -translate-y-1/2" style="top: {{ $index / (count($growth['scale']) - 1) * 100 }}%">{{ $value }}</span>
                        @endforeach
                    </div>

                    <div class="relative h-44 flex-1 border-b border-l border-gray-300 sm:h-56 dark:border-white/20">
                        @foreach (array_slice($growth['scale'], 0, -1) as $index => $value)
                            <div class="absolute inset-x-0 border-t border-dashed border-gray-200 dark:border-white/10" style="top: {{ $index / (count($growth['scale']) - 1) * 100 }}%"></div>
                        @endforeach

                        <div class="relative flex h-full items-end justify-around gap-1 px-1 sm:gap-3 sm:px-2">
                            @foreach ($growth['bars'] as $bar)
                                <div class="flex h-full min-w-0 flex-1 items-end justify-center">
                                    <div class="relative w-full max-w-12 rounded-t-md bg-emerald-600 dark:bg-emerald-500" style="height: {{ $bar['percent'] }}%" title="{{ $bar['label'] }}: {{ $bar['count'] }}">
                                        @if ($bar['percent'] > 0)
                                            <span class="absolute inset-x-0 -top-5 text-center text-[10px] font-medium whitespace-nowrap text-gray-600 sm:text-xs dark:text-gray-300">{{ $bar['count'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex gap-2">
                    <div class="w-9 shrink-0 sm:w-10"></div>
                    <div class="flex flex-1 justify-around gap-1 px-1 sm:gap-3 sm:px-2">
                        @foreach ($growth['bars'] as $bar)
                            <span class="min-w-0 flex-1 text-center text-[10px] leading-tight text-gray-600 sm:text-xs dark:text-gray-400">{{ $bar['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- The classes of the last day anything joined. --}}
            <div class="flex flex-col gap-2">
                <p class="text-center text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('Classes getting questions now') }}</p>

                <div class="flex flex-1 flex-wrap content-center items-center justify-center gap-2 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    @forelse ($growth['chips'] as $chip)
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-sm text-gray-800 dark:bg-white/5 dark:text-gray-200">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            {{ $chip }}
                        </span>
                    @empty
                        <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                    @endforelse
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
