<x-filament-panels::page>
    <form
        x-data="{ trySubmit: () => {}, kept: { forget: () => {} } }"
        x-init="
            // Answers reach the server only on submit or when time runs out:
            // kept in this tab until then, so a refresh or a change of
            // language keeps them — ticked again once wire:model is bound.
            $nextTick(() => kept = window.keepExamAnswers({ root: $el, storageKey: @js('qb:attempt:' . $attempt->id) }));

            const timer = window.startExamTimer({
                root: $el,
                // Put the answers on record the moment time runs out, so a
                // later submit cannot carry anything changed after it.
                onTimeUp: () => $wire.saveAnswers(),
            });

            // Warns about unanswered questions before an early submit.
            trySubmit = window.guardExamSubmit({
                root: $el,
                isTimeUp: timer.isTimeUp,
                submit: () => {
                    kept.forget();
                    $wire.submit();
                },
            });
        "
        x-on:submit.prevent="trySubmit()"
    >
        <x-exam-paper :attempt="$attempt" livewire timer-class="qb-exam-bar--below-topbar">
            <x-filament::button type="submit" class="mt-8">
                {{ __('Submit exam') }}
            </x-filament::button>
        </x-exam-paper>
    </form>

    @vite('resources/js/exam-timer.js')
</x-filament-panels::page>
