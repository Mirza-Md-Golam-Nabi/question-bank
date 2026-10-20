@props(['stimulus', 'heading' => null])

@php
    /**
     * A shared stimulus, shown once above the MCQs that use it: the heading
     * naming their numbers (QuestionBlocks::heading()), then its text and
     * image. The same on the exam, its result, the print and the picker.
     *
     * @var \App\Models\QuestionStimulus|\App\Models\BoardMcqStimulus $stimulus
     * @var string|null $heading  Null where the questions aren't numbered (a question card).
     */
@endphp

<div {{ $attributes->class('qb-question-stimulus') }}>
    @if ($heading)
        <p class="qb-question-stimulus-heading">{{ $heading }}</p>
    @endif

    <div class="qb-question-text">{!! $stimulus->text !!}</div>

    <x-question-image :path="$stimulus->image" />
</div>
