@props(['match', 'earlierNumber' => null, 'note' => null])

@php
    /**
     * The question already there that another one looks like
     * (DuplicateQuestionFinder) — how alike, and that question itself with
     * its answers, to compare: shown at once when it is one already there,
     * behind the arrow when it is one earlier in the same JSON (it is also
     * further up the list). Shown in the "Add from JSON" preview and when
     * an Admin approves.
     *
     * @var \App\Services\DuplicateMatch $match
     * @var int|null $earlierNumber  When it is a question earlier in the same JSON, its number there.
     * @var string|null $note  What becomes of the question ("Will not be added.").
     */
    $title = match (true) {
        $match->isExact && $earlierNumber !== null => __('The same as question :number above.', ['number' => $earlierNumber]),
        $match->isExact => __('The same question is already there.'),
        $earlierNumber !== null => __('Possible duplicate — :percent% alike question :number above.', ['percent' => $match->percent, 'number' => $earlierNumber]),
        default => __('Possible duplicate — :percent% alike this question already there:', ['percent' => $match->percent]),
    };
@endphp

<div
    x-data="{ open: @js($earlierNumber === null) }"
    {{ $attributes->class(['qb-question-duplicate', 'qb-question-duplicate--same' => $match->isExact]) }}
>
    <div class="qb-question-duplicate-head">
        <p class="qb-question-duplicate-title">
            {{ collect([$match->subject ? $match->subject.':' : null, $title, $note])->filter()->join(' ') }}
        </p>

        <button
            type="button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open"
            x-bind:title="open ? @js(__('Hide the question')) : @js(__('Show the question'))"
            class="qb-question-duplicate-toggle"
        >
            <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5 transition" x-bind:class="open && 'rotate-180'" />
        </button>
    </div>

    <div x-show="open" x-cloak>
        @include('filament.support.questions.question-body', ['question' => $match->question, 'showAnswers' => true])
    </div>
</div>
