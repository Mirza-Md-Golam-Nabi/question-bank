@php
    /**
     * Questions to read, with their answers and explanations: the MCQs
     * (a shared stimulus once above its questions) and then the CQs, each
     * numbered within its kind — a past paper, or a teacher's exam. The
     * window's own title says what it is.
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\Question|\App\Models\BoardMcqQuestion> $mcqQuestions
     * @var \Illuminate\Support\Collection<int, \App\Models\Question|\App\Models\BoardCqQuestion> $cqQuestions
     */
@endphp

<div class="space-y-8">
    @foreach ([__('MCQ questions') => $mcqQuestions, __('CQ questions') => $cqQuestions] as $heading => $questions)
        @if ($questions->isNotEmpty())
            <section class="space-y-5">
                <h3 class="text-base font-semibold">{{ $heading }}</h3>

                @foreach (\App\Filament\Support\QuestionBlocks::blocks($questions) as $block)
                    @if ($block['stimulus'])
                        <x-question-stimulus :stimulus="$block['stimulus']" :heading="$block['heading']" class="border-t pt-4 dark:border-white/10" />
                    @endif

                    @foreach ($block['questions'] as ['number' => $number, 'question' => $question])
                        <div class="flex gap-2 border-t pt-4 dark:border-white/10">
                            <span class="shrink-0 font-medium">{{ $number }}.</span>
                            <div class="min-w-0 flex-1">
                                @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true, 'showStimulus' => false])
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </section>
        @endif
    @endforeach
</div>
