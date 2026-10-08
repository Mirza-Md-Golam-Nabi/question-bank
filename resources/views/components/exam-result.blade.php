@props([
    'attempt',
    'alwaysShowAnswers' => false,
])

@php
    /**
     * An attempt's result: always the score, and — only once the exam's
     * answers may be shown (Exam::showsAnswersToStudents()) — every
     * question with the correct answer and the student's own. Until then
     * the slot is shown instead: the page's own note on how to come back
     * for the answers. Used by the guest result page and the Student
     * panel's result page.
     *
     * @var \App\Models\ExamAttempt $attempt
     * @var bool $alwaysShowAnswers  For the exam's teacher, who may see the answers whether or not they are released to students.
     */
    $showsAnswers = $alwaysShowAnswers || $attempt->exam->showsAnswersToStudents();
    $answersByQuestion = $showsAnswers ? $attempt->answers->keyBy('question_id') : collect();
@endphp

<p class="qb-result-score">
    {{ __('Score') }}: <strong>{{ $attempt->total_score }}</strong> / {{ $attempt->exam->total_marks }}
</p>

@if ($showsAnswers)
    <div class="qb-result-list">
        @foreach ($attempt->shuffledQuestions() as $index => $question)
            <x-exam-result-question
                class="qb-result-item"
                :question="$question"
                :answer="$answersByQuestion->get($question->id)"
                :number="$index + 1"
            />
        @endforeach
    </div>
@else
    {{-- Only the score for now: showing the answers while the exam is still
         open would hand them to everyone who has yet to sit it. --}}
    <div class="qb-result-notice">
        <p class="qb-result-notice-title">{{ __('The answers are not available yet.') }}</p>
        <p class="qb-result-notice-text">{{ $slot }}</p>
    </div>
@endif
