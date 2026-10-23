@props([
    /** @var array<int, array{label: string, done: bool, url: string, page: \App\Enums\GuidePage|null}> $steps */
    'steps',
])

{{-- "Get started" on a new user's dashboard (OnboardingChecklist): each step
     ticks itself once done; closing it hides it for good. --}}
<div
    x-data="{ open: true }"
    x-show="open"
    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-5 dark:bg-gray-900 dark:ring-white/10"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Get started') }}</h2>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                {{ __(':done of :total done', ['done' => \App\Enums\Locale::current()->writeDigits((string) collect($steps)->where('done', true)->count()), 'total' => \App\Enums\Locale::current()->writeDigits((string) count($steps))]) }}
            </p>
        </div>

        <button
            type="button"
            x-on:click="open = false; fetch(@js(route('onboarding.dismiss')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, Accept: 'application/json' } })"
            class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white"
        >
            {{ __('Do not show again') }}
        </button>
    </div>

    <ol class="mt-4 space-y-2">
        @foreach ($steps as $step)
            <li class="flex items-center gap-3">
                @if ($step['done'])
                    <x-filament::icon icon="heroicon-s-check-circle" class="size-5 shrink-0 text-success-600 dark:text-success-400" />
                    <span class="flex-1 text-sm text-gray-500 line-through dark:text-gray-400">{{ $step['label'] }}</span>
                @else
                    <x-filament::icon icon="heroicon-o-stop-circle" class="size-5 shrink-0 text-gray-400" />
                    <a href="{{ $step['url'] }}" class="flex-1 text-sm font-medium text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">{{ $step['label'] }}</a>
                    @if ($step['page'])
                        <a href="{{ \App\Filament\Support\Pages\HelpCenter::getUrl(['page' => $step['page']->value]) }}" class="shrink-0 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ __('How?') }}</a>
                    @endif
                @endif
            </li>
        @endforeach
    </ol>
</div>
