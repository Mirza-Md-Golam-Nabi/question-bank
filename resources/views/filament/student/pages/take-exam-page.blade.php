<x-filament-panels::page>
    <form
        x-data="{ trySubmit: () => {} }"
        x-init="
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
                submit: () => $wire.submit(),
            });
        "
        x-on:submit.prevent="trySubmit()"
    >
        {{-- Livewire must not re-render the running clock or undo the lock. --}}
        <div wire:ignore>
            <x-exam-timer :seconds="$attempt->secondsRemaining()" class="qb-exam-bar--below-topbar">
                {{ __(':minutes minutes', ['minutes' => $attempt->exam->duration_minutes]) }} &middot; {{ __(':marks marks', ['marks' => $attempt->exam->total_marks]) }}
            </x-exam-timer>
        </div>

        {{-- Locked as a whole when the clock runs out; the submit button stays outside it. --}}
        <fieldset class="qb-exam-answers space-y-8" data-qb-answers wire:ignore.self>
        @foreach ($attempt->shuffledQuestions() as $index => $question)
            <div class="border-t pt-4" data-qb-question>
                {{-- The text is stored as its own <p>, so the number sits beside it rather than inside one. --}}
                <div class="mb-3 flex gap-1.5 font-medium">
                    <span class="shrink-0">{{ $index + 1 }}.</span>
                    <div class="qb-question-text min-w-0">{!! $question->question_text !!}</div>
                </div>

                @if ($question->question_type->value === 'mcq')
                    <div class="space-y-2">
                        @foreach ($question->options as $option)
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option['option'] }}">
                                <span>{!! $option['option'] !!}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea wire:model="answers.{{ $question->id }}" rows="4" class="fi-input w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-white/20"
                        placeholder="{{ __('Write your answer...') }}"></textarea>
                @endif
            </div>
        @endforeach
        </fieldset>

        <x-filament::button type="submit" class="mt-8">
            {{ __('Submit exam') }}
        </x-filament::button>

        {{-- Opened and closed by the page script, so Livewire leaves it alone. --}}
        <div wire:ignore>
            <x-exam-submit-warning />
        </div>
    </form>

    @vite('resources/js/exam-timer.js')
</x-filament-panels::page>
