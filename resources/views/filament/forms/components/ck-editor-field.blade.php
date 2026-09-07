<x-filament-forms::field-wrapper :field="$field">
    <div
        wire:ignore
        wire:key="{{ $getStatePath() }}-ck-editor-wrapper"
        x-data="{
            init() {
                // Guards against duplicate editor instances if this field is
                // ever re-initialized (e.g. toggled visible/hidden and back)
                // without the wire:key forcing a full remove+recreate.
                if (this.$refs.host.dataset.ckInitialized) {
                    return;
                }

                this.$refs.host.dataset.ckInitialized = 'true';

                window.createQuestionCkEditor(this.$refs.host, {
                    initialData: @js($getState() ?? ''),
                    compact: @js($field->isCompact()),
                });
            },
        }"
        x-on:ck-data-changed.debounce.500ms="$wire.$set($statePath, $event.detail.html)"
    >
        <div x-ref="host" class="qb-ck-editor-host @if ($field->isCompact()) qb-ck-editor-host--compact @endif"></div>
    </div>
</x-filament-forms::field-wrapper>
