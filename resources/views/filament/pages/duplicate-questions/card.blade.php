@php
    /**
     * One held question in the "Possible duplicates" card view
     * (DuplicateQuestions): where it is, the question whole with its
     * answer, and under it the questions already in the chapter it looks
     * like — each with how alike and its answers.
     */
    use App\Models\QuestionDuplicateMatch;

    $question = $getRecord();
    $matches = $question->duplicateMatches
        ->filter(fn (QuestionDuplicateMatch $match): bool => $match->matchedQuestion !== null)
        ->map(fn (QuestionDuplicateMatch $match) => $match->toDuplicateMatch())
        ->values()
        ->all();
@endphp

<div class="w-full space-y-3 py-1">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ $question->chapter?->classSubject?->label() }} · {{ $question->chapter?->name }}
    </p>

    @include('filament.support.pages.question-picker.question-card', ['question' => $question, 'showAnswers' => true, 'reviewing' => true])

    @if ($matches !== [])
        <div class="space-y-2">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Questions already there it looks like') }}</p>

            @include('filament.support.questions.duplicate-warnings', ['matches' => $matches])
        </div>
    @endif
</div>
