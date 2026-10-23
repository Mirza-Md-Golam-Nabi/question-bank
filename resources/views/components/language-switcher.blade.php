@props([
    'variant' => 'light',
    // Pages that keep their own choice (SetLocale::SCOPES), e.g. "exam".
    'for' => null,
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
            href="{{ route('locale.switch', ['locale' => $locale->value, 'for' => $for]) }}"
            lang="{{ $locale->value }}"
            data-qb-language-switch
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

{{-- On a plain page (not a panel's), switching language reloads it: what was
     typed into its forms is kept in this tab and put back once it reloads in
     the new language — by form and field name. A form that keeps its own
     state (the exam paper — keepExamAnswers()) says so with
     data-qb-own-state. Twice, a moment apart, for a choice that fills the
     next one (a class's sections). --}}
@if ($variant !== 'panel')
    @once
        <script>
            (() => {
                const key = 'qb:language-switch:' + location.pathname;
                const forms = () => [...document.querySelectorAll('form:not([data-qb-own-state])')];
                const fields = (form) => [...form.querySelectorAll('input, select, textarea')]
                    .filter((field) => field.name && field.name !== '_token' && ! ['hidden', 'password', 'submit', 'button', 'file'].includes(field.type));

                document.addEventListener('click', (event) => {
                    if (! event.target.closest('[data-qb-language-switch]')) {
                        return;
                    }

                    const typed = forms().flatMap((form, index) => fields(form)
                        .filter((field) => ['radio', 'checkbox'].includes(field.type) ? field.checked : field.value !== '')
                        .map((field) => [index, field.name, field.value]));

                    try {
                        sessionStorage.setItem(key, JSON.stringify(typed));
                    } catch {
                        // Storage unavailable: the page just reloads blank.
                    }
                });

                const putBack = (typed) => typed.forEach(([index, name, value]) => {
                    const form = forms()[index];

                    (form ? fields(form) : []).filter((field) => field.name === name).forEach((field) => {
                        if (['radio', 'checkbox'].includes(field.type)) {
                            if (field.value !== value || field.checked) {
                                return;
                            }

                            field.checked = true;
                        } else if (field.value !== value) {
                            field.value = value;
                        } else {
                            return;
                        }

                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });

                document.addEventListener('DOMContentLoaded', () => {
                    let typed = null;

                    try {
                        typed = JSON.parse(sessionStorage.getItem(key) ?? 'null');
                        sessionStorage.removeItem(key);
                    } catch {
                        return;
                    }

                    if (Array.isArray(typed) && typed.length) {
                        putBack(typed);
                        setTimeout(() => putBack(typed), 200);
                    }
                });
            })();
        </script>
    @endonce
@endif
