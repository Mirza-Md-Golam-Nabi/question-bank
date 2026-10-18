@php
    /**
     * Preview step of the "Add from JSON" window: the questions exactly as
     * they will be stored, drawn by the same KaTeX as everywhere else. The
     * field's own value is how many formulas KaTeX refused, reported back
     * so the window won't add questions with a broken formula.
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\Question|\App\Models\BoardMcqQuestion|\App\Models\BoardCqQuestion> $questions
     */
    $unrenderableMessage = __(':count formula(s) could not be drawn — they are shown in red.');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{-- Keyed by its content: new JSON means a new element, so the
         formulas are drawn and counted afresh. --}}
    <div
        wire:key="{{ $getId() }}-{{ md5($questions->toJson()) }}"
        wire:ignore
        x-data="{ unrenderable: 0 }"
        x-init="
            window.renderKatexEmbeds?.($el);
            unrenderable = $el.querySelectorAll('.katex-error').length;
            $wire.$set(@js($getStatePath()), unrenderable, false);
        "
        class="qb-import-preview"
    >
        <p class="qb-question-preview-label">
            {{ trans_choice(':count Question|:count Questions', $questions->count()) }}
        </p>

        <p
            x-cloak
            x-show="unrenderable > 0"
            x-text="@js($unrenderableMessage).replace(':count', unrenderable)"
            class="qb-import-preview-warning"
        ></p>

        @foreach (\App\Filament\Support\QuestionBlocks::blocks($questions) as $block)
            @if ($block['stimulus'])
                <x-question-stimulus :stimulus="$block['stimulus']" :heading="$block['heading']" />
            @endif

            @foreach ($block['questions'] as ['number' => $number, 'question' => $question])
                <div class="qb-question-preview">
                    <p class="qb-question-preview-label">
                        {{-- A past paper's question has no difficulty. --}}
                        {{ collect([$number, $question->question_type->getLabel(), $question->difficulty?->getLabel()])->filter()->join(' · ') }}
                    </p>

                    @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true, 'showStimulus' => false])
                </div>
            @endforeach
        @endforeach
    </div>
</x-dynamic-component>
