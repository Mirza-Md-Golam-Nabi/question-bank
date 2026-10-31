@php
    /**
     * One row of a list of questions in its card view
     * (SwitchesCardAndTableView) — a chapter's questions and a past paper's
     * alike: the whole question with its answer, numbered through the list,
     * a shared stimulus once above the first of its questions (QuestionBlocks).
     */
    use App\Models\Question;

    $question = $getRecord();
    $card = $getLivewire()->questionCard($question);
@endphp

<div class="w-full min-w-0 space-y-2 py-1">
    @if ($card['stimulus'])
        <x-question-stimulus :stimulus="$card['stimulus']" :heading="$card['heading']" />
    @endif

    @include('filament.support.pages.question-picker.question-card', [
        'question' => $question,
        'showAnswers' => true,
        'reviewing' => $question instanceof Question,
        'number' => $card['number'],
        'showStimulus' => false,
    ])
</div>
