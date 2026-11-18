@php
    /**
     * One student's paper as the exam's teacher sees it (the "view answers"
     * panel of the results page).
     *
     * @var \App\Models\ExamAttempt|null $attempt
     */
@endphp

@if ($attempt)
    {{-- Name on the left, phone/email on the right — the same line the student's own exam page shows. --}}
    <x-exam-participant :attempt="$attempt" class="mb-4 text-gray-950 dark:text-white" />

    {{-- An OMR check has no questions to show: its sheet against the key. --}}
    @if ($attempt->exam->isOmrCheck())
        <x-omr-answer-sheet :attempt="$attempt" />
    @else
        <x-exam-result :attempt="$attempt" always-show-answers />
    @endif
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('This result could not be found.') }}</p>
@endif
