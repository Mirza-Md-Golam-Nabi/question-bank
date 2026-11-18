@props(['attempt'])

@php
    /**
     * One scanned sheet of an OMR check (CLAUDE.md rule 10.12) against its
     * answer key — the "view answers" panel of the results page, for a
     * check whose questions are only numbers: each row the question's
     * number and its circles, the right one green, the student's other
     * pick red; more than one filled in, or none, said in words.
     *
     * @var \App\Models\ExamAttempt $attempt
     */
    use App\Enums\Locale;
    use App\Filament\Support\QuestionDisplay;
    use App\Models\AnswerKeyQuestion;
    use App\Models\AttemptAnswer;
    use App\Support\OmrSheet;

    $answers = $attempt->answersByQuestion();
    $digits = fn (string $text): string => Locale::current()->writeDigits($text);
@endphp

<p class="qb-result-score">
    {{ __('Score') }}: <strong>{{ $digits(QuestionDisplay::marks((float) $attempt->total_score)) }}</strong> / {{ $digits(QuestionDisplay::marks($attempt->exam->scoredMarks())) }}
</p>

<div class="qb-answer-key mt-3">
    @foreach ($attempt->exam->answerKeyQuestions() as $question)
        @php
            $answer = $answers->get(AttemptAnswer::keyFor($question));
            $given = $answer?->student_answer;
            $picked = AnswerKeyQuestion::optionIndexOf($given);
        @endphp
        <div class="qb-answer-key-row">
            <span class="qb-answer-key-number">{{ $digits((string) $question->id) }}</span>
            @foreach ($question->options as $index => $option)
                <span @class([
                    'qb-answer-key-bubble',
                    'qb-answer-key-bubble--correct' => $option['is_correct'],
                    'qb-answer-key-bubble--wrong' => $picked === $index && ! $option['is_correct'],
                ])>{{ QuestionDisplay::letter($index) }}</span>
            @endforeach
            @if ($given === OmrSheet::SEVERAL_FILLED)
                <span class="qb-answer-key-note">{{ __('More than one') }}</span>
            @elseif ($given === null)
                <span class="qb-answer-key-note">{{ __('Blank') }}</span>
            @elseif ($answer->is_correct)
                <span class="qb-answer-key-note">✓</span>
            @endif
        </div>
    @endforeach
</div>
