@props(['dark' => false])

@php
    $currentLocale = app()->getLocale();
@endphp

<div
    role="group"
    aria-label="{{ __('Language') }}"
    {{ $attributes->class([
        'inline-flex shrink-0 items-center gap-0.5 rounded-full p-0.5 text-xs font-medium ring-1',
        'bg-white/10 ring-white/15' => $dark,
        'bg-gray-100 ring-gray-200' => ! $dark,
    ]) }}
>
    @foreach (\App\Enums\Locale::cases() as $locale)
        <a
            href="{{ route('locale.switch', ['locale' => $locale->value]) }}"
            lang="{{ $locale->value }}"
            @if ($locale->value === $currentLocale) aria-current="true" @endif
            @class([
                'rounded-full px-2.5 py-1 transition sm:px-3',
                'bg-white text-slate-950 shadow-sm' => $locale->value === $currentLocale,
                'text-slate-300 hover:text-white' => $locale->value !== $currentLocale && $dark,
                'text-gray-500 hover:text-gray-950' => $locale->value !== $currentLocale && ! $dark,
            ])
        >
            {{ $locale->getLabel() }}
        </a>
    @endforeach
</div>
