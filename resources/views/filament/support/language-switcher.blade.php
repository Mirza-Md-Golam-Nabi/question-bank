@php
    $currentLocale = app()->getLocale();
@endphp

<div @class(['flex items-center', 'mt-6 justify-center' => $centered ?? false])>
    <div
        role="group"
        aria-label="{{ __('Language') }}"
        class="inline-flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 text-xs font-medium ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
    >
        @foreach (\App\Enums\Locale::cases() as $locale)
            <a
                href="{{ route('locale.switch', ['locale' => $locale->value]) }}"
                lang="{{ $locale->value }}"
                @if ($locale->value === $currentLocale) aria-current="true" @endif
                @class([
                    'rounded-md px-2.5 py-1 transition',
                    'bg-white text-gray-950 shadow-sm dark:bg-white/15 dark:text-white' => $locale->value === $currentLocale,
                    'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $locale->value !== $currentLocale,
                ])
            >
                {{ $locale->getLabel() }}
            </a>
        @endforeach
    </div>
</div>
