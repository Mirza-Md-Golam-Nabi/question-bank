@props([
    'attempt',
    'livewire' => false,
    'savedAnswers' => [],
    'timerClass' => null,
])

@php
    /**
     * The exam a student sits — the heading, whose paper it is, the
     * countdown bar, the questions in this attempt's own order, and the
     * "unanswered questions" dialog. Everything here is the same for a
     * guest and a logged-in student, so both see the same page. The guest
     * exam page (a plain HTML form) and the Student panel's exam page (a
     * Livewire component) both render it; the slot is the page's own submit
     * button.
     *
     * The two pages differ only in how an answer field is bound: a form
     * field name for the plain form, `wire:model` for Livewire. The
     * `wire:ignore` attributes below stop Livewire from re-rendering the
     * running clock or undoing the lock, and mean nothing to a plain page.
     *
     * @var \App\Models\ExamAttempt $attempt
     * @var bool $livewire  Bind answers with wire:model instead of form field names.
     * @var iterable<int, string> $savedAnswers  question id => answer already on record (plain form only).
     */
    use App\Enums\QuestionType;

    $bind = fn (int $questionId): string => $livewire
        ? 'wire:model="answers.' . $questionId . '"'
        : 'name="answers[' . $questionId . ']"';
@endphp

<x-exam-heading :exam="$attempt->exam" class="mb-4" />
<x-exam-participant :attempt="$attempt" class="mb-3" />

{{-- `display: contents` keeps this wrapper out of the layout: the bar is
     sticky, and a sticky element only travels within its parent box — inside
     a wrapper no taller than itself it would scroll away with the page. --}}
<div wire:ignore style="display: contents">
    <x-exam-timer :seconds="$attempt->secondsRemaining()" :class="$timerClass">
        <x-exam-summary :exam="$attempt->exam" />
    </x-exam-timer>
</div>

{{-- Locked as a whole when the clock runs out; the submit button stays outside it.
     Questions and options are a size smaller on a phone, full size from a tablet up. --}}
<fieldset class="qb-exam-answers space-y-6 text-sm sm:space-y-8 sm:text-base" data-qb-answers wire:ignore.self>
    @foreach ($attempt->shuffledQuestions() as $index => $question)
        @php
            $savedAnswer = $savedAnswers[$question->id] ?? null;
        @endphp

        <div class="border-t pt-4" data-qb-question>
            {{-- The text is stored as its own <p>, so the number sits beside it rather than inside one. --}}
            <div class="mb-3 flex gap-1.5 font-medium">
                <span class="shrink-0">{{ $index + 1 }}.</span>
                <div class="qb-question-text min-w-0">{!! $question->question_text !!}</div>
            </div>

            @if ($question->question_type === QuestionType::Mcq)
                <div class="space-y-2">
                    @foreach ($question->options as $option)
                        <label class="flex items-center gap-2">
                            <input type="radio" {!! $bind($question->id) !!} value="{{ $option['option'] }}" @checked($savedAnswer === $option['option'])>
                            <span>{!! $option['option'] !!}</span>
                        </label>
                    @endforeach
                </div>
            @else
                <textarea {!! $bind($question->id) !!} rows="4" class="qb-input" placeholder="{{ __('Write your answer...') }}">{{ $savedAnswer }}</textarea>
            @endif
        </div>
    @endforeach
</fieldset>

{{-- The page's own submit button; the wrapper lets it shrink on a phone. --}}
<div class="qb-exam-submit">
    {{ $slot }}
</div>

{{-- Opened and closed by the page script, so Livewire leaves it alone. --}}
<div wire:ignore>
    <x-exam-submit-warning />
</div>
