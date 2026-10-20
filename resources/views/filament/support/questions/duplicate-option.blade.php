@php
    /**
     * One possible duplicate in the "Add from JSON" list to tick: its label,
     * and a button that scrolls the preview to that question and flashes
     * it. The button sits inside the checkbox's label, so its click must
     * not tick the box.
     *
     * @var string $label  "Question 12 — 92% alike"
     * @var string $anchor  The id of the question's box in the preview.
     */
@endphp

<span class="qb-duplicate-option">
    <span>{{ $label }}</span>

    <button
        type="button"
        class="qb-duplicate-option-jump"
        title="{{ __('Go to the question') }}"
        aria-label="{{ __('Go to the question') }}"
        x-on:click.prevent.stop="
            const question = document.getElementById(@js($anchor))
            question?.scrollIntoView({ behavior: 'smooth', block: 'start' })
            question?.classList.add('qb-question-preview--flash')
            setTimeout(() => question?.classList.remove('qb-question-preview--flash'), 1600)
        "
    >
        <x-filament::icon icon="heroicon-m-arrow-up-circle" class="h-5 w-5" />
    </button>
</span>
