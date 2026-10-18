@php
    /**
     * Read-only body of one question — where it came from (a past paper's),
     * text/stimulus, image, then the MCQ options or the four CQ
     * sub-questions. Shared by the Teacher's question picker, the printable
     * question paper and the past-paper views.
     *
     * @var \App\Models\Question|\App\Models\BoardMcqQuestion|\App\Models\BoardCqQuestion $question
     * @var bool $showAnswers  Marks the correct MCQ option, and shows a past paper's explanations and CQ answers.
     * @var bool $showStimulus  Shows a shared stimulus above the question — off where the view already lays questions out under their stimulus (QuestionBlocks).
     */
    use App\Enums\QuestionType;
    use App\Filament\Support\QuestionBlocks;
    use App\Filament\Support\QuestionDisplay;
    use App\Models\BoardMcqQuestion;

    $showAnswers ??= false;
    $showStimulus ??= true;
    $stimulus = $showStimulus ? QuestionBlocks::stimulusOf($question) : null;
    $questionImageUrl = QuestionDisplay::imageUrl($question->question_image);
@endphp

<div class="qb-question-body">
    <x-question-source :question="$question" />

    @if ($stimulus)
        <x-question-stimulus :stimulus="$stimulus" />
    @endif

    <div class="qb-question-text">{!! $question->question_text !!}</div>

    @if ($questionImageUrl)
        <img src="{{ $questionImageUrl }}" alt="" class="qb-question-image" loading="lazy">
    @endif

    @if ($question->question_type === QuestionType::Mcq)
        <ol class="qb-question-options">
            @foreach ($question->options ?? [] as $index => $option)
                @php
                    $isCorrect = $showAnswers && ($option['is_correct'] ?? false);
                    $optionImageUrl = QuestionDisplay::imageUrl($option['image'] ?? null);
                @endphp

                <li @class(['qb-question-option', 'qb-question-option--correct' => $isCorrect])>
                    {{-- A slot in front of every option, so the letters stay aligned. --}}
                    @if ($showAnswers)
                        <span class="qb-question-option-tick" @if ($isCorrect) aria-label="{{ __('Correct answer') }}" @endif>{{ $isCorrect ? '✓' : '' }}</span>
                    @endif

                    <span class="qb-question-option-letter">{{ QuestionDisplay::letter($index) }}</span>
                    <div class="qb-question-option-content">
                        <div>{!! $option['option'] ?? '' !!}</div>

                        @if ($optionImageUrl)
                            <img src="{{ $optionImageUrl }}" alt="" class="qb-question-image" loading="lazy">
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($showAnswers && $question instanceof BoardMcqQuestion)
            <x-question-explanation :text="$question->explanation" />
        @endif
    @else
        <ol class="qb-question-parts">
            @foreach ($question->cqParts as $index => $part)
                @php
                    $partImageUrl = QuestionDisplay::imageUrl($part->part_image);
                @endphp

                <li class="qb-question-part">
                    <span class="qb-question-option-letter">{{ QuestionDisplay::letter($index) }}</span>
                    <div class="qb-question-option-content">
                        <div>{!! $part->part_text !!}</div>

                        @if ($partImageUrl)
                            <img src="{{ $partImageUrl }}" alt="" class="qb-question-image" loading="lazy">
                        @endif

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
