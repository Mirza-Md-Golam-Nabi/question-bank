@props([
    'variant' => 'light',
])

@php
    /**
     * The language toggle, wherever it appears. One markup, three looks:
     *  - light: on a light public page (guest exam pages)
     *  - dark:  on a dark public header (landing page)
     *  - panel: inside a Filament panel, following its light/dark mode
     */
    $currentLocale = app()->getLocale();

    $styles = [
        'light' => [
            'group' => 'rounded-full bg-gray-100 ring-gray-200',
            'item' => 'rounded-full sm:px-3',
            'active' => 'bg-white text-slate-950 shadow-sm',
            'inactive' => 'text-gray-500 hover:text-gray-950',
        ],
        'dark' => [
            'group' => 'rounded-full bg-white/10 ring-white/15',
            'item' => 'rounded-full sm:px-3',
            'active' => 'bg-white text-slate-950 shadow-sm',
            'inactive' => 'text-slate-300 hover:text-white',
        ],
        'panel' => [
            'group' => 'rounded-lg bg-gray-100 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10',
            'item' => 'rounded-md',
            'active' => 'bg-white text-gray-950 shadow-sm dark:bg-white/15 dark:text-white',
            'inactive' => 'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white',
        ],
    ][$variant];
@endphp

<div
    role="group"
    aria-label="{{ __('Language') }}"
    {{ $attributes->class(['inline-flex shrink-0 items-center gap-0.5 p-0.5 text-xs font-medium ring-1', $styles['group']]) }}
>
    @foreach (\App\Enums\Locale::cases() as $locale)
        <a
            href="{{ route('locale.switch', ['locale' => $locale->value]) }}"
            lang="{{ $locale->value }}"
            @if ($locale->value === $currentLocale) aria-current="true" @endif
            @class([
                'px-2.5 py-1 transition',
                $styles['item'],
                $styles['active'] => $locale->value === $currentLocale,
                $styles['inactive'] => $locale->value !== $currentLocale,
            ])
        >
            {{ $locale->getLabel() }}
        </a>
    @endforeach
</div>
