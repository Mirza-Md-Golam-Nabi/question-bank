@props([
    'question',
    'answer' => null,
    'number' => null,
    'verdict' => true,
])

@php
    /**
     * One question on an exam result — the guest result page and the
     * Student panel's result page both render it. Shows every MCQ option,
     * with the correct one (or ones) on a success background and the
     * student's own wrong pick on a danger background; a past paper's
     * question without a correct answer says so, and how it was marked.
     *
     * @var \App\Models\Question|\App\Models\BoardMcqQuestion $question  Shown with its explanation; a past paper's MCQ also with where it came from.
     * @var \App\Models\AttemptAnswer|null $answer  Null when the question was left unanswered.
     * @var bool $verdict  The line with how it was marked — left out on the mistakes list, which isn't one exam's result.
     */
    use App\Enums\QuestionType;
    use App\Filament\Support\QuestionDisplay;

    $isMcq = $question->question_type === QuestionType::Mcq;
    $isAnswered = filled($answer?->student_answer);
    $hasCorrectAnswer = $isMcq && $question->hasCorrectAnswer();
@endphp

<div {{ $attributes->class(['qb-result-question']) }}>
    <x-question-source :question="$question" />

    <div class="qb-result-question-head">
        @if ($number !== null)
            <span class="qb-result-question-number">{{ $number }}.</span>
        @endif

        <div class="qb-question-text">{!! $question->question_text !!}</div>
    </div>

    <x-question-image :path="$question->question_image" />

    @if ($isMcq)
        <ol class="qb-question-options qb-result-options">
            @foreach ($question->options ?? [] as $index => $option)
                @php
                    $isCorrect = (bool) ($option['is_correct'] ?? false);
                    $isChosen = $isAnswered && $answer->student_answer === $question::answerValueOf($option);
                @endphp

                <li @class([
                    'qb-question-option',
                    'qb-result-option',
                    'qb-result-option--correct' => $isCorrect,
                    'qb-result-option--wrong' => $isChosen && ! $isCorrect && $hasCorrectAnswer,
                ])>
                    <span class="qb-question-option-letter">{{ QuestionDisplay::letter($index) }}</span>
                    <div class="qb-question-option-content">
                        <div>{!! $option['option'] ?? '' !!}</div>

                        <x-question-image :path="$option['image'] ?? null" option />
                    </div>

                    {{-- Colour alone shouldn't carry the meaning. --}}
                    @if ($isCorrect)
                        <span class="qb-result-option-tag">{{ $isChosen ? __('Your answer') . ' ✓' : __('Correct answer') }}</span>
                    @elseif ($isChosen)
                        <span class="qb-result-option-tag">{{ __('Your answer') }} {{ $hasCorrectAnswer ? '✗' : '' }}</span>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($verdict)
        <p @class([
            'qb-result-verdict',
            'qb-result-verdict--correct' => $answer?->is_correct === true,
            'qb-result-verdict--wrong' => $isAnswered && $answer?->is_correct === false,
        ])>
            @if (! $hasCorrectAnswer)
                {{ __('This question has no correct answer.') }}
                {{ $answer?->obtained_marks > 0
                    ? __('Everyone gets its :marks marks.', ['marks' => QuestionDisplay::marks($answer->obtained_marks)])
                    : __('It is not counted in the marks.') }}
            @elseif (! $isAnswered)
                {{ __('Not answered') }} — {{ __(':marks marks', ['marks' => 0]) }}
            @else
                {{ $answer->is_correct ? __('Correct') : __('Incorrect') }} — {{ __(':marks marks', ['marks' => QuestionDisplay::marks($answer->obtained_marks)]) }}
            @endif
        </p>
        @endif

        <x-question-explanation :text="$question->explanation" />
    @else
        <p class="qb-result-verdict">{{ __('Pending manual grading') }}</p>
    @endif
</div>
