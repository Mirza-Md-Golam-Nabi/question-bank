@php
    /**
     * The format of the "Add from JSON" window, shown in full with a copy
     * button: the rules in words and an example, ready to hand to an AI tool.
     *
     * @var string $guide
     */
@endphp

<div
    x-data="{
        copied: false,
        async copy() {
            const text = this.$refs.guide.textContent;

            try {
                await navigator.clipboard.writeText(text);
            } catch {
                // No clipboard access (an http:// address, an old browser):
                // select the text and use the browser's own copy command.
                const range = document.createRange();
                range.selectNodeContents(this.$refs.guide);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                document.execCommand('copy');
                selection.removeAllRanges();
            }

            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        },
    }"
    class="qb-question-preview"
>
    <div class="qb-import-format-head">
        <p class="qb-question-preview-label">{{ __('Format — copy it and give it to the AI') }}</p>

        <x-filament::button size="xs" color="gray" icon="heroicon-o-clipboard-document" x-on:click="copy()">
            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
        </x-filament::button>
    </div>

    <pre x-ref="guide" dir="ltr" class="qb-import-format-text">{{ $guide }}</pre>
</div>
