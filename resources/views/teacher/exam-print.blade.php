@php
    use App\Filament\Support\QuestionDisplay;
    use App\Filament\Teacher\Resources\Exams\ExamResource;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $exam->title }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/katex-embed-renderer.js'])

    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        .qb-paper {
            color: #111827;
        }

        .qb-paper-question {
            display: flex;
            gap: 0.5rem;
            padding: 0.5rem 0;
            break-inside: avoid;
        }

        .qb-paper-number {
            flex: none;
            min-width: 2rem;
            font-weight: 600;
        }

        .qb-paper-number::after {
            content: '.';
        }

        .qb-answer-key {
            margin: 0 auto;
            border-collapse: collapse;
            font-size: 1rem;
        }

        .qb-answer-key th,
        .qb-answer-key td {
            min-width: 3rem;
            padding: 0.3rem 0.75rem;
            border: 1px solid #6b7280;
            text-align: center;
        }

        .qb-answer-key th {
            background: #f3f4f6;
            font-weight: 600;
        }

        .qb-answer-key-answer {
            font-weight: 600;
        }

        /* A thicker line between one number/answer pair and the next. */
        .qb-answer-key th:nth-child(2n):not(:last-child),
        .qb-answer-key td:nth-child(2n):not(:last-child) {
            border-right-width: 3px;
        }

        .qb-answer-key tr {
            break-inside: avoid;
        }

        /* On a phone screen all five pairs still fit side by side. */
        @media screen and (max-width: 640px) {
            .qb-answer-key {
                font-size: 0.8125rem;
            }

            .qb-answer-key th,
            .qb-answer-key td {
                min-width: 0;
                padding: 0.25rem 0.3rem;
            }
        }

        @media print {
            .qb-answer-key th {
                background: #fff !important;
            }

            html,
            body {
                background: #fff !important;
            }

            .qb-no-print {
                display: none !important;
            }

            .qb-paper {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }

            /* Printed in black; the tick alone marks the right answer. */
            .qb-question-option--correct {
                color: #000 !important;
            }
        }
    </style>
</head>
<body class="font-bangla bg-gray-100 min-h-screen">
    <div class="qb-no-print sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="qb-btn qb-btn--primary text-sm">
                {{ __('Print / Save as PDF') }}
            </button>

            {{-- The other forms of this paper: not the one on screen, and no
                 answer key for a CQ-only paper (CQ has no answers to list). --}}
            @foreach ([
                ['answers' => null, 'label' => __('Questions only'), 'hidden' => ! $showAnswers && ! $isAnswerKey],
                ['answers' => 1, 'label' => __('With answers'), 'hidden' => $showAnswers],
                ['answers' => 'key', 'label' => __('Answer key only'), 'hidden' => $isAnswerKey || $mcqQuestions->isEmpty()],
            ] as $copy)
                @unless ($copy['hidden'])
                    <a href="{{ \App\Filament\Support\CurrentPanel::route('exams.print', array_filter(['exam' => $exam, 'answers' => $copy['answers']])) }}"
                       class="qb-btn qb-btn--outline text-sm">
                        {{ $copy['label'] }}
                    </a>
                @endunless
            @endforeach

            <a href="{{ ExamResource::getUrl('index') }}"
               class="ms-auto text-sm font-medium text-gray-600 hover:underline">
                {{ __('Back to exams') }}
            </a>
        </div>

        <p class="mx-auto mt-2 max-w-3xl text-xs text-gray-500">
            {{ __('To get a PDF: press the button, then choose "Save as PDF" as the printer (on iPhone: Share → Save to Files).') }}
        </p>

        {{-- Facebook/Messenger/Instagram open links in a browser that cannot print. --}}
        <p id="qb-in-app-browser-warning" hidden
           class="mx-auto mt-2 max-w-3xl rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
            {{ __('Printing does not work inside an in-app browser. Open this page in Chrome or Safari first.') }}
        </p>
    </div>

    <main class="qb-paper mx-auto my-6 max-w-3xl bg-white p-6 shadow sm:p-10">
        <header class="mb-6 border-b border-gray-300 pb-4 text-center">
            <h1 class="text-xl font-bold">{{ $exam->title }}</h1>

            @if ($exam->classSubject)
                <p class="mt-1">{{ $exam->classSubject->academicClass->name }} &middot; {{ $exam->subject->name }}</p>
            @elseif ($exam->subject)
                <p class="mt-1">{{ $exam->subject->name }}</p>
            @endif

            <p class="mt-2 flex justify-between text-sm">
                <span>{{ __('Time') }}: {{ __(':minutes minutes', ['minutes' => $exam->duration_minutes]) }}</span>
                <span>{{ __('Total marks') }}: {{ QuestionDisplay::marks($totalMarks) }}</span>
            </p>

            @if ($showAnswers)
                <p class="mt-2 text-sm font-semibold">{{ __('Answer copy — for the teacher') }}</p>
            @elseif ($isAnswerKey)
                <p class="mt-2 text-sm font-semibold">{{ __('Answer key') }}</p>
            @endif
        </header>

        @if ($isAnswerKey)
            @include('teacher.partials.exam-answer-key', ['mcqQuestions' => $mcqQuestions])
        @else
        @if ($mcqQuestions->isNotEmpty())
            <section class="mb-8">
                @if ($cqQuestions->isNotEmpty())
                    <h2 class="mb-2 text-center text-base font-bold">{{ __('MCQ questions') }}</h2>
                @endif

                {{-- A shared stimulus is printed once, above the questions it is for. --}}
                @foreach (\App\Filament\Support\QuestionBlocks::blocks($mcqQuestions) as $block)
                    @if ($block['stimulus'])
                        <x-question-stimulus :stimulus="$block['stimulus']" :heading="$block['heading']" />
                    @endif

                    @foreach ($block['questions'] as ['number' => $number, 'question' => $question])
                        <div class="qb-paper-question">
                            <span class="qb-paper-number">{{ $number }}</span>
                            <div class="min-w-0 flex-1">
                                @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => $showAnswers, 'showStimulus' => false])
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </section>
        @endif

        @if ($cqQuestions->isNotEmpty())
            <section>
                @if ($mcqQuestions->isNotEmpty())
                    <h2 class="mb-2 text-center text-base font-bold">{{ __('CQ questions') }}</h2>
                @endif

                @foreach ($cqQuestions->values() as $index => $question)
                    <div class="qb-paper-question">
                        <span class="qb-paper-number">{{ $index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            {{-- Only a past paper's CQ can carry answers. --}}
                            @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => $showAnswers])
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
        @endif
    </main>

    <script>
        if (/FBAN|FBAV|FB_IAB|Instagram|Messenger/i.test(navigator.userAgent)) {
            document.getElementById('qb-in-app-browser-warning').hidden = false;
        }
    </script>
</body>
</html>
