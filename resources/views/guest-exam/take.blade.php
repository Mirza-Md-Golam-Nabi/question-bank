@extends('layouts.guest-exam')

@section('title', $attempt->exam->title)

@section('hide-home-link', true)

@section('content')
    <form id="qb-exam-form" data-qb-own-state method="POST" action="{{ route('guest-exam.submit', $attempt) }}">
        @csrf

        <x-exam-paper :attempt="$attempt" :saved-answers="$savedAnswers">
            <button type="submit" class="qb-btn qb-btn--primary mt-8">{{ __('Submit exam') }}</button>
        </x-exam-paper>
    </form>

    @vite('resources/js/exam-timer.js')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('qb-exam-form');

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

            // Answers only reach the server on submit: kept in this tab
            // until then, so a refresh or a change of language keeps them.
            const kept = window.keepExamAnswers({ root: form, storageKey: @js('qb:attempt:' . $attempt->id) });

            // Warns about unanswered questions before an early submit.
            const trySubmit = window.guardExamSubmit({
                root: form,
                isTimeUp: timer.isTimeUp,
                submit: () => {
                    kept.forget();

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
