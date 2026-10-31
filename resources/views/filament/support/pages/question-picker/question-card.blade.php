@php
    /**
     * What a question card shows — its type, topic, difficulty and marks,
     * then the question itself, with its answer where the page shows answers
     * while picking (not to a student about to sit it). The same in the
     * question picker's selectable list and final view, and on the lists
     * that review a question instead of picking it (question-table-card) — a
     * past paper's question there, which has no type, topic or difficulty of
     * its own to show, only its marks.
     *
     * @var \App\Models\Question|\App\Models\BoardMcqQuestion|\App\Models\BoardCqQuestion $question
     * @var bool $showAnswers
     * @var bool $reviewing  Shows where the question is in review, its version and who wrote it (unless the viewer did).
     * @var int|null $number  Its number, on a list that numbers its questions.
     * @var bool $showStimulus  See question-body.
     */
    use App\Enums\DuplicateCheckStatus;
    use App\Enums\QuestionStatus;
    use App\Enums\QuestionType;
    use App\Filament\Support\QuestionDisplay;
    use App\Models\Question;

    $reviewing ??= false;
    $number ??= null;
    $showStimulus ??= true;
@endphp

<div class="min-w-0 flex-1">
    <div class="mb-2 flex flex-wrap items-center gap-2">
        @if ($number)
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $number }}.</span>
        @endif

        @if ($question instanceof Question)
            <x-filament::badge :color="$question->question_type === QuestionType::Mcq ? 'info' : 'warning'">
                {{ $question->question_type->getLabel() }}
            </x-filament::badge>

            @if ($question->topic)
                <x-filament::badge color="gray">{{ $question->topic->name }}</x-filament::badge>
            @endif

            <x-filament::badge color="gray">{{ $question->difficulty->getLabel() }}</x-filament::badge>

            @if ($reviewing)
                <x-filament::badge :color="$question->status->getColor()">{{ $question->status->getLabel() }}</x-filament::badge>

                {{-- Still on the background duplicate check, or waiting for the
                     Admin's word on it — never which question it looks like. --}}
                @if ($question->duplicate_status !== DuplicateCheckStatus::Clear)
                    <x-filament::badge :color="$question->duplicate_status->getColor()" icon="heroicon-m-document-duplicate">
                        {{ $question->duplicate_status->getLabel() }}
                    </x-filament::badge>
                @endif

                @if ($question->version > 1)
                    <x-filament::badge color="gray">{{ __('v') }}{{ $question->version }}</x-filament::badge>
                @endif
            {{-- The teacher's own question, not reviewed yet: usable on their
                 exams, invisible to everyone else. --}}
            @elseif ($question->status === QuestionStatus::Pending)
                <x-filament::badge color="warning" icon="heroicon-m-clock">{{ __('Yours — awaiting approval') }}</x-filament::badge>
            @endif

            {{-- How the teacher's own students have done on it, where the list
                 was loaded with those numbers (TeacherQuestionStats::addTo()). --}}
            @if ($question->students_answered)
                <x-filament::badge :color="$question->students_wrong > 0 ? 'danger' : 'success'" icon="heroicon-m-users">
                    {{ __(':wrong of :answered wrong', ['wrong' => $question->students_wrong, 'answered' => $question->students_answered]) }}
                </x-filament::badge>
            @endif
        @endif

        <span class="ms-auto text-xs font-medium text-gray-500 dark:text-gray-400">
            @if ($reviewing && $question->creator && $question->created_by !== auth()->id())
                {{ $question->creator->name }} ·
            @endif
            {{ __(':marks marks', ['marks' => QuestionDisplay::marks($question->marks)]) }}
        </span>
    </div>

    @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => $showAnswers, 'showStimulus' => $showStimulus])
</div>
