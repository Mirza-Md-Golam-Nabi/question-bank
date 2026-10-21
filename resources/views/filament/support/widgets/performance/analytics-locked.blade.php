<x-filament-widgets::widget>
    <x-filament::section>
        <div class="relative overflow-hidden rounded-xl">
            {{-- A picture of the analytics from made-up figures, blurred: shows what they look like, reveals nothing. --}}
            <div class="pointer-events-none select-none blur-[3px] opacity-70" aria-hidden="true">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach ([['8', 'bg-sky-500'], ['72%', 'bg-emerald-500'], ['68%', 'bg-amber-500'], ['5', 'bg-rose-500']] as [$value, $color])
                        <div class="rounded-xl p-4 text-white {{ $color }}">
                            <div class="h-2 w-16 rounded bg-white/60"></div>
                            <div class="mt-3 text-2xl font-bold">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <svg viewBox="0 0 380 120" class="h-32 w-full">
                            <polyline points="{{ $this->samplePoints() }}" fill="none" stroke="#0ea5e9" stroke-width="3" stroke-linejoin="round" />
                        </svg>
                    </div>

                    <div class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        @foreach ($this->sampleSubjects() as $subject)
                            <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300">
                                <span class="w-24 shrink-0 truncate">{{ $subject['name'] }}</span>
                                <span class="h-3 flex-1 rounded bg-gray-100 dark:bg-white/10">
                                    <span class="block h-3 rounded bg-primary-500" style="width: {{ $subject['percent'] }}%"></span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="absolute inset-0 flex items-center justify-center bg-white/40 p-4 dark:bg-gray-900/50">
                <div class="max-w-md rounded-2xl bg-white p-6 text-center shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-lock-closed" class="h-5 w-5" />
                    </div>
                    <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">{{ __('Performance analytics') }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $this->pitch() }}</p>
                    <x-filament::button tag="a" :href="$this->subscriptionUrl()" class="mt-4" icon="heroicon-o-sparkles">
                        {{ __('See plans') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
