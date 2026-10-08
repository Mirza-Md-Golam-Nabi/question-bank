@props([
    'question',
    'answer' => null,
    'number' => null,
])

@php
    /**
     * One question on an exam result — the guest result page and the
     * Student panel's result page both render it. Shows every MCQ option,
     * with the correct one on a success background and the student's own
     * wrong pick on a danger background.
     *
     * @var \App\Models\Question $question
     * @var \App\Models\AttemptAnswer|null $answer  Null when the question was left unanswered.
     */
    use App\Enums\QuestionType;
    use App\Filament\Support\QuestionDisplay;

    $isMcq = $question->question_type === QuestionType::Mcq;
    $isAnswered = filled($answer?->student_answer);
    $questionImageUrl = QuestionDisplay::imageUrl($question->question_image);
@endphp

<div {{ $attributes->class(['qb-result-question']) }}>
    <div class="qb-result-question-head">
        @if ($number !== null)
            <span class="qb-result-question-number">{{ $number }}.</span>
        @endif

        <div class="qb-question-text">{!! $question->question_text !!}</div>
    </div>

    @if ($questionImageUrl)
        <img src="{{ $questionImageUrl }}" alt="" class="qb-question-image" loading="lazy">
    @endif

    @if ($isMcq)
        <ol class="qb-question-options qb-result-options">
            @foreach ($question->options ?? [] as $index => $option)
                @php
                    $isCorrect = (bool) ($option['is_correct'] ?? false);
                    $isChosen = $isAnswered && $answer->student_answer === ($option['option'] ?? null);
                    $optionImageUrl = QuestionDisplay::imageUrl($option['image'] ?? null);
                @endphp

                <li @class([
                    'qb-question-option',
                    'qb-result-option',
                    'qb-result-option--correct' => $isCorrect,
                    'qb-result-option--wrong' => $isChosen && ! $isCorrect,
                ])>
                    <span class="qb-question-option-letter">{{ QuestionDisplay::letter($index) }}</span>
                    <div class="qb-question-option-content">
                        <div>{!! $option['option'] ?? '' !!}</div>

                        @if ($optionImageUrl)
                            <img src="{{ $optionImageUrl }}" alt="" class="qb-question-image" loading="lazy">
                        @endif
                    </div>

                    {{-- Colour alone shouldn't carry the meaning. --}}
                    @if ($isCorrect)
                        <span class="qb-result-option-tag">{{ $isChosen ? __('Your answer') . ' ✓' : __('Correct answer') }}</span>
                    @elseif ($isChosen)
                        <span class="qb-result-option-tag">{{ __('Your answer') }} ✗</span>
                    @endif
                </li>
            @endforeach
        </ol>

        <p @class([
            'qb-result-verdict',
            'qb-result-verdict--correct' => $answer?->is_correct === true,
            'qb-result-verdict--wrong' => $isAnswered && $answer?->is_correct === false,
        ])>
            @if (! $isAnswered)
                {{ __('Not answered') }} — {{ __(':marks marks', ['marks' => 0]) }}
            @else
                {{ $answer->is_correct ? __('Correct') : __('Incorrect') }} — {{ __(':marks marks', ['marks' => QuestionDisplay::marks($answer->obtained_marks)]) }}
            @endif
        </p>
    @else
        <p class="qb-result-verdict">{{ __('Pending manual grading') }}</p>
    @endif
</div>
