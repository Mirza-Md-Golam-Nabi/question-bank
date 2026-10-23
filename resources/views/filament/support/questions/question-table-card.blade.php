@php
    /**
     * One row of a chapter's question list in its card view
     * (ListQuestionsByChapterPage) — the whole question, as the question
     * picker shows it, with its answer and where it is in review.
     */
@endphp

<div class="w-full py-1">
    @include('filament.support.pages.question-picker.question-card', ['question' => $getRecord(), 'showAnswers' => true, 'reviewing' => true])
</div>
