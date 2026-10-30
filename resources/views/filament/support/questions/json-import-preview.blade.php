@php
    /**
     * Preview step of the "Add from JSON" window: the questions exactly as
     * they will be stored, drawn by the same KaTeX as everywhere else. The
     * field's own value is how many formulas KaTeX refused, reported back
     * so the window won't add questions with a broken formula.
     *
     * Under each question that looks like one already there (or one
     * earlier in the JSON) is the question it looks like.
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\Question|\App\Models\BoardMcqQuestion|\App\Models\BoardCqQuestion> $questions
     * @var array<int, \App\Services\DuplicateMatch> $matches  By the question's place in $questions.
     * @var array<string, string> $notTaken  The images given but not taken, by file name, with why.
     * @var bool $leftToAdmin  Whether every question is added, one that looks like a question already there waiting for the Admin.
     */
    $unrenderableMessage = __(':count formula(s) could not be drawn — they are shown in red.');

    // The places of the questions left out without asking: all of their
    // unit is already there — none when the Admin decides.
    $alreadyThere = $leftToAdmin ? [] : collect(\App\Services\ImportedQuestions::units($questions))
        ->filter(fn (array $unit): bool => \App\Services\ImportedQuestions::isAlreadyThere($unit, $matches))
        ->flatten()
        ->all();
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

        @if ($notTaken !== [])
            <div class="qb-import-images-not-taken">
                <p class="qb-import-images-not-taken-title">
                    {{ __('Images given but not taken (:count):', ['count' => count($notTaken)]) }}
                </p>

                <ul>
                    @foreach ($notTaken as $name => $why)
                        <li><span class="font-mono">{{ $name }}</span> — {{ $why }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach (\App\Filament\Support\QuestionBlocks::blocks($questions) as $block)
            @if ($block['stimulus'])
                <x-question-stimulus :stimulus="$block['stimulus']" :heading="$block['heading']" />
            @endif

            @foreach ($block['questions'] as ['number' => $number, 'question' => $question])
                {{-- Its id is where the possible-duplicates list jumps to. --}}
                <div class="qb-question-preview" id="{{ \App\Filament\Support\QuestionJsonImportAction::previewAnchor($number) }}">
                    <p class="qb-question-preview-label">
                        {{-- A past paper's question has no difficulty. --}}
                        {{ collect([$number, $question->question_type->getLabel(), $question->difficulty?->getLabel()])->filter()->join(' · ') }}
                    </p>

                    @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true, 'showStimulus' => false])

                    @if ($match = $matches[$number - 1] ?? null)
                        @php
                            $earlier = $match->question->exists ? false : $questions->search($match->question, strict: true);
                        @endphp

                        <x-question-duplicate
                            :match="$match"
                            :earlier-number="$earlier === false ? null : $earlier + 1"
                            :note="match (true) {
                                $leftToAdmin => __('It will be added; the Admin decides on it under “Possible duplicates”.'),
                                in_array($number - 1, $alreadyThere, strict: true) => __('It will not be added.'),
                                default => null,
                            }"
                        />
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</x-dynamic-component>
