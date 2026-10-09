@php
    /**
     * Preview step of the "Add from JSON" window: the questions exactly as
     * they will be stored, drawn by the same KaTeX as everywhere else. The
     * field's own value is how many formulas KaTeX refused, reported back
     * so the window won't add questions with a broken formula.
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\Question> $questions
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

        @foreach ($questions as $index => $question)
            <div class="qb-question-preview">
                <p class="qb-question-preview-label">
                    {{ $index + 1 }} · {{ $question->question_type->getLabel() }} · {{ $question->difficulty->getLabel() }}
                </p>

                @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true])
            </div>
        @endforeach
    </div>
</x-dynamic-component>
