@php
    /**
     * One row of a past paper's MCQ or CQ list in its card view
     * (PaperQuestionsRelationManager) — its number in the whole list, its
     * marks, and the whole question with its answers, under the paper's own
     * heading.
     */
    use App\Filament\Support\QuestionDisplay;

    $question = $getRecord();
@endphp

<div class="w-full min-w-0 space-y-2 py-1">
    <div class="flex items-center justify-between gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
        <span>{{ $getLivewire()->questionNumber($question) }}.</span>
        <span>{{ __(':marks marks', ['marks' => QuestionDisplay::marks($question->marks)]) }}</span>
    </div>

    @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true])
</div>
