@extends('layouts.guest-exam')

@section('title', $attempt->exam->title)

@section('content')
    <form id="qb-exam-form" method="POST" action="{{ route('guest-exam.submit', $attempt) }}">
        @csrf

        <x-exam-paper :attempt="$attempt" :saved-answers="$savedAnswers">
            <button type="submit" class="qb-btn qb-btn--primary mt-8">{{ __('Submit exam') }}</button>
        </x-exam-paper>
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
