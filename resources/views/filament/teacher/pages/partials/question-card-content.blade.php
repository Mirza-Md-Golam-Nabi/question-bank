@php
    /**
     * What a question card shows in the question picker — its type, topic,
     * difficulty and marks, then the question itself with its answer. The
     * same in the selectable list and in the final view.
     *
     * @var \App\Models\Question $question
     */
    use App\Enums\QuestionType;
    use App\Filament\Support\QuestionDisplay;
@endphp

<div class="min-w-0 flex-1">
    <div class="mb-2 flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$question->question_type === QuestionType::Mcq ? 'info' : 'warning'">
            {{ $question->question_type->getLabel() }}
        </x-filament::badge>

        @if ($question->topic)
            <x-filament::badge color="gray">{{ $question->topic->name }}</x-filament::badge>
        @endif

        <x-filament::badge color="gray">{{ $question->difficulty->getLabel() }}</x-filament::badge>

        <span class="ms-auto text-xs font-medium text-gray-500 dark:text-gray-400">
            {{ __(':marks marks', ['marks' => QuestionDisplay::marks($question->marks)]) }}
        </span>
    </div>

    @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true])
</div>
