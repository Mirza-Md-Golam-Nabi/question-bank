@extends('layouts.guest-exam')

@section('title', $attempt->exam->title)

@section('content')
    <h1 class="text-2xl font-bold mb-2">{{ $attempt->exam->title }}</h1>
    <form id="qb-exam-form" method="POST" action="{{ route('guest-exam.submit', $attempt) }}">
        @csrf

        <x-exam-timer :seconds="$attempt->secondsRemaining()">
            {{ __(':minutes minutes', ['minutes' => $attempt->exam->duration_minutes]) }} &middot; {{ __(':marks marks', ['marks' => $attempt->exam->total_marks]) }}
        </x-exam-timer>

        {{-- Locked as a whole when the clock runs out; the submit button stays outside it. --}}
        <fieldset class="qb-exam-answers space-y-8" data-qb-answers>
        @foreach ($attempt->shuffledQuestions() as $index => $question)
            <div class="border-t pt-4" data-qb-question>
                {{-- The text is stored as its own <p>, so the number sits beside it rather than inside one. --}}
                <div class="mb-3 flex gap-1.5 font-medium">
                    <span class="shrink-0">{{ $index + 1 }}.</span>
                    <div class="qb-question-text min-w-0">{!! $question->question_text !!}</div>
                </div>

                @if ($question->question_type->value === 'mcq')
                    <div class="space-y-2">
                        @foreach ($question->options as $option)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option['option'] }}"
                                       @checked(($savedAnswers[$question->id] ?? null) === $option['option'])>
                                <span>{!! $option['option'] !!}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea name="answers[{{ $question->id }}]" rows="4" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        placeholder="{{ __('Write your answer...') }}">{{ $savedAnswers[$question->id] ?? '' }}</textarea>
                @endif
            </div>
        @endforeach
        </fieldset>

        <button type="submit" class="mt-8 rounded-lg bg-amber-500 text-white px-4 py-2 font-medium hover:bg-amber-600">
            {{ __('Submit exam') }}
        </button>

        <x-exam-submit-warning />
    </form>

    @vite('resources/js/exam-timer.js')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('qb-exam-form');

            // Answers only reach the server on submit, so keep them in this
            // tab's storage meanwhile — a refresh then brings them back
            // instead of presenting a blank paper.
            const storageKey = @js('qb:guest-attempt:' . $attempt->id);
            const fields = () => [...form.querySelectorAll('[name^="answers["]')];

            try {
                const stored = JSON.parse(sessionStorage.getItem(storageKey) ?? '{}');

                fields().forEach((field) => {
                    if (! (field.name in stored)) {
                        return;
                    }

                    if (field.type === 'radio') {
                        field.checked = field.value === stored[field.name];
                    } else {
                        field.value = stored[field.name];
                    }
                });

                form.addEventListener('input', () => {
                    const answers = {};

                    fields().forEach((field) => {
                        if (field.type !== 'radio' || field.checked) {
                            answers[field.name] = field.value;
                        }
                    });

                    sessionStorage.setItem(storageKey, JSON.stringify(answers));
                });
            } catch {
                // Storage unavailable (private mode): the exam still works, a refresh just starts blank.
            }

            const timer = window.startExamTimer({
                root: form,
                // Put the answers on record the moment time runs out, so a
                // later submit can't carry anything changed after it.
                onTimeUp: () => fetch(@js(route('guest-exam.answers', $attempt)), {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json' },
                    keepalive: true,
                }).catch(() => {}),
            });

            // Warns about unanswered questions before an early submit.
            const trySubmit = window.guardExamSubmit({
                root: form,
                isTimeUp: timer.isTimeUp,
                submit: () => {
                    try {
                        sessionStorage.removeItem(storageKey);
                    } catch {
                        // Nothing was stored in the first place.
                    }

                    // form.submit() does not fire the submit event, so this cannot loop.
                    form.submit();
                },
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                trySubmit();
            });
        });
    </script>
@endsection
