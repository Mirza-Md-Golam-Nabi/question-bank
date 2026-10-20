@php
    /**
     * Read-only body of one question — where it came from (a past paper's),
     * text/stimulus, image, then the MCQ options or the four CQ
     * sub-questions. Shared by the Teacher's question picker, the printable
     * question paper and the past-paper views.
     *
     * @var \App\Models\Question|\App\Models\BoardMcqQuestion|\App\Models\BoardCqQuestion $question
     * @var bool $showAnswers  Marks the correct MCQ option, and shows its explanation and a past paper's CQ answers.
     * @var bool $showStimulus  Shows a shared stimulus above the question — off where the view already lays questions out under their stimulus (QuestionBlocks).
     */
    use App\Enums\QuestionType;
    use App\Filament\Support\QuestionBlocks;
    use App\Filament\Support\QuestionDisplay;

    $showAnswers ??= false;
    $showStimulus ??= true;
    $stimulus = $showStimulus ? QuestionBlocks::stimulusOf($question) : null;
@endphp

<div class="qb-question-body">
    <x-question-source :question="$question" />

    @if ($stimulus)
        <x-question-stimulus :stimulus="$stimulus" />
    @endif

    <div class="qb-question-text">{!! $question->question_text !!}</div>

    <x-question-image :path="$question->question_image" />

    @if ($question->question_type === QuestionType::Mcq)
        <ol class="qb-question-options">
            @foreach ($question->options ?? [] as $index => $option)
                @php
                    $isCorrect = $showAnswers && ($option['is_correct'] ?? false);
                @endphp

                <li @class(['qb-question-option', 'qb-question-option--correct' => $isCorrect])>
                    {{-- A slot in front of every option, so the letters stay aligned. --}}
                    @if ($showAnswers)
                        <span class="qb-question-option-tick" @if ($isCorrect) aria-label="{{ __('Correct answer') }}" @endif>{{ $isCorrect ? '✓' : '' }}</span>
                    @endif

                    <span class="qb-question-option-letter">{{ QuestionDisplay::letter($index) }}</span>
                    <div class="qb-question-option-content">
                        <div>{!! $option['option'] ?? '' !!}</div>

                        <x-question-image :path="$option['image'] ?? null" option />
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($showAnswers)
            <x-question-explanation :text="$question->explanation" />
        @endif
    @else
        <ol class="qb-question-parts">
            @foreach ($question->cqParts as $index => $part)
                <li class="qb-question-part">
                    <span class="qb-question-option-letter">{{ QuestionDisplay::letter($index) }}</span>
                    <div class="qb-question-option-content">
                        <div>{!! $part->part_text !!}</div>

                        <x-question-image :path="$part->part_image" />

                        @if ($showAnswers)
                            <x-question-explanation :text="$part->answer ?? null" :title="__('Answer')" />
                        @endif
                    </div>
                    <span class="qb-question-part-marks">{{ QuestionDisplay::marks($part->marks) }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
