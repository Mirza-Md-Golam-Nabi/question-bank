@php
    /**
     * A past paper to read, with its answers and explanations: its MCQs
     * (when shown) and its CQs, each numbered within its kind. The paper's
     * own title is the heading around it, so the questions don't each
     * repeat where they came from.
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\BoardMcqQuestion> $mcqQuestions
     * @var \Illuminate\Support\Collection<int, \App\Models\BoardCqQuestion> $cqQuestions
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
