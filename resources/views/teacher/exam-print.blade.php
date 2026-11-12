@php
    use App\Enums\Locale;
    use App\Filament\Support\CurrentPanel;
    use App\Filament\Support\QuestionBlocks;
    use App\Filament\Support\QuestionDisplay;
    use App\Filament\Teacher\Resources\Exams\ExamResource;
    use App\Support\PrintSettings;

    $hasMcq = $mcqQuestions->isNotEmpty();
    $hasCq = $cqQuestions->isNotEmpty();
    // Numbers in the paper's own language (Bangla digits on a Bangla paper).
    $digits = fn (string $text): string => Locale::current()->writeDigits($text);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $exam->title }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/katex-embed-renderer.js', 'resources/js/print-settings.js'])

    {{-- The page size, margins and page numbers — rewritten by print-settings.js. --}}
    <style id="qb-page-rule">@page { size: A4; margin: 12mm 10mm; }</style>

    <style>
        /*
         * The paper. Everything the print settings change is a custom
         * property or a data attribute on .qb-paper, set by print-settings.js.
         */
        .qb-paper {
            --qb-columns: 2;
            --qb-column-gap: 20px;
            --qb-question-gap: 8px;
            --qb-font-size: 14px;
            --qb-institution-size: 26px;
            --qb-paper-width: 210mm;
            --qb-margin: 12mm 10mm;

            /* Its real size; a narrow screen shows it smaller (zoom, print-settings.js). */
            width: var(--qb-paper-width);
            padding: var(--qb-margin);
            color: #111827;
            font-family: var(--qb-font-family, inherit);
            font-size: var(--qb-font-size);
            text-align: var(--qb-text-align, left);
        }

        .qb-paper .qb-question-body {
            font-size: inherit;
        }

        .qb-paper-institution {
            font-size: var(--qb-institution-size);
            font-weight: 700;
            line-height: 1.25;
        }

        [data-print-part='class']:not([hidden]) + [data-print-part='subject']:not([hidden])::before {
            content: ' · ';
        }

        .qb-paper-instructions {
            margin-bottom: 0.5rem;
            font-size: 0.9em;
            font-style: italic;
            white-space: pre-line;
        }

        .qb-paper-question {
            display: flex;
            gap: 0.5rem;
            padding-bottom: var(--qb-question-gap);
            break-inside: avoid;
        }

        .qb-paper-number {
            flex: none;
            min-width: 1.75em;
            font-weight: 600;
        }

        .qb-paper-number::after {
            content: '.';
        }

        /* The MCQ part in columns, read down one column and on to the next. */
        .qb-paper-columns {
            column-count: var(--qb-columns);
            column-gap: var(--qb-column-gap);
        }

        .qb-paper[data-column-divider='1'] .qb-paper-columns {
            column-rule: 1px solid #9ca3af;
        }

        .qb-paper-columns .qb-question-stimulus {
            break-inside: avoid;
        }

        .qb-paper[data-options-per-row='1'] .qb-question-options {
            grid-template-columns: minmax(0, 1fr);
        }

        .qb-paper[data-options-per-row='2'] .qb-question-options {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .qb-paper[data-options-per-row='4'] .qb-question-options {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        /* How an option's letter is written — on the paper and in the settings' samples. */
        [data-option-style] .qb-question-option .qb-question-option-letter {
            min-width: 0;
        }

        [data-option-style='paren'] .qb-question-option .qb-question-option-letter::before {
            content: '(';
        }

        [data-option-style='paren'] .qb-question-option .qb-question-option-letter::after,
        [data-option-style='bracket'] .qb-question-option .qb-question-option-letter::after {
            content: ')';
        }

        [data-option-style='circle'] .qb-question-option .qb-question-option-letter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5em;
            height: 1.5em;
            border: 1px solid currentColor;
            border-radius: 9999px;
            font-size: 0.85em;
            line-height: 1;
        }

        [data-option-style='circle'] .qb-question-option .qb-question-option-letter::after {
            content: none;
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

        /*
         * The settings: a column beside the paper on a wide screen, a drawer
         * over it on a phone.
         */
        .qb-print-settings {
            position: fixed;
            inset: 0 0 0 auto;
            z-index: 30;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: min(22rem, 90vw);
            padding: 1rem;
            overflow-y: auto;
            background: #fff;
            box-shadow: -4px 0 16px rgb(0 0 0 / 0.15);
            transform: translateX(100%);
            transition: transform 0.2s ease;
        }

        .qb-print-page[data-settings-open] .qb-print-settings {
            transform: none;
        }

        .qb-print-settings-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .qb-print-settings-close {
            margin-inline-start: auto;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            color: #6b7280;
        }

        .qb-print-settings-group {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: #f9fafb;
        }

        .qb-print-settings-group h3 {
            margin: -0.75rem -0.75rem 0;
            padding: 0.5rem 0.75rem;
            border-top: 2px solid #059669;
            border-radius: 0.75rem 0.75rem 0 0;
            background: #ecfdf5;
            font-weight: 700;
        }

        .qb-print-field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.875rem;
        }

        .qb-print-field > span {
            display: flex;
            justify-content: space-between;
            font-weight: 500;
        }

        .qb-print-field :is(select, textarea, input[type='number']) {
            width: 100%;
            padding: 0.375rem 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #fff;
            font-size: 0.875rem;
        }

        .qb-print-field input[type='range'] {
            accent-color: #059669;
        }

        .qb-print-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .qb-print-switch input {
            appearance: none;
            position: relative;
            flex: none;
            width: 2.5rem;
            height: 1.375rem;
            border-radius: 9999px;
            background: #d1d5db;
            cursor: pointer;
            transition: background 0.15s;
        }

        .qb-print-switch input::after {
            content: '';
            position: absolute;
            top: 0.1875rem;
            left: 0.1875rem;
            width: 1rem;
            height: 1rem;
            border-radius: 9999px;
            background: #fff;
            transition: transform 0.15s;
        }

        .qb-print-switch input:checked {
            background: #059669;
        }

        .qb-print-switch input:checked::after {
            transform: translateX(1.125rem);
        }

        .qb-print-choices {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }

        .qb-print-choices button {
            flex: 1 0 auto;
            min-width: 2.75rem;
            padding: 0.375rem 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #fff;
            font-size: 0.875rem;
        }

        .qb-print-choices button[aria-pressed='true'] {
            border-color: #059669;
            background: #ecfdf5;
            color: #047857;
            font-weight: 600;
            box-shadow: inset 0 0 0 1px #059669;
        }

        .qb-print-column-icon {
            display: inline-flex;
            gap: 3px;
        }

        .qb-print-column-icon span {
            width: 0.625rem;
            height: 1rem;
            border-radius: 2px;
            background: currentColor;
            opacity: 0.45;
        }

        @media screen and (min-width: 1024px) {
            .qb-print-settings {
                inset: 0 auto 0 0;
                width: 22rem;
                box-shadow: 1px 0 0 #e5e7eb;
                transform: none;
            }

            .qb-print-settings-close,
            .qb-print-settings-open {
                display: none !important;
            }

            .qb-print-main {
                margin-inline-start: 22rem;
            }
        }

        /* On a phone screen all five answer-key pairs still fit side by side. */
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

            .qb-print-main {
                margin: 0 !important;
            }

            /* The page's own margins (@page) frame it on paper. */
            .qb-paper {
                zoom: 1 !important;
                width: auto !important;
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
<body class="qb-print-page font-bangla bg-gray-100 min-h-screen">
    @include('teacher.partials.print-settings-panel', compact('printSettings', 'institutionNames', 'defaultInstructions', 'hasMcq', 'hasCq'))

    <div class="qb-print-main">
        <div class="qb-no-print sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
            <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
                <button type="button" data-settings-toggle
                        class="qb-print-settings-open qb-btn qb-btn--outline text-sm">
                    ⚙ {{ __('Print settings') }}
                </button>

                <button type="button" onclick="window.print()"
                        class="qb-btn qb-btn--primary text-sm">
                    {{ __('Print / Save as PDF') }}
                </button>

                {{-- The other forms of this paper: not the one on screen, and no
                     answer key for a CQ-only paper (CQ has no answers to list). --}}
                @foreach ([
                    ['answers' => null, 'label' => __('Questions only'), 'hidden' => ! $showAnswers && ! $isAnswerKey],
                    ['answers' => 1, 'label' => __('With answers'), 'hidden' => $showAnswers],
                    ['answers' => 'key', 'label' => __('Answer key only'), 'hidden' => $isAnswerKey || ! $hasMcq],
                ] as $copy)
                    @unless ($copy['hidden'])
                        <a href="{{ CurrentPanel::route('exams.print', array_filter(['exam' => $exam, 'answers' => $copy['answers']])) }}"
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

        <main class="qb-paper mx-auto my-6 bg-white shadow">
            <header class="mb-4 border-b border-gray-300 pb-3 text-center">
                <p class="qb-paper-institution" data-print-institution hidden></p>

                <h1 class="text-[1.25em] font-bold" data-print-part="exam_title">{{ $exam->title }}</h1>

                @if ($exam->classSubject)
                    <p class="mt-1">
                        <span data-print-part="class">{{ $exam->classSubject->academicClass->name }}</span>
                        <span data-print-part="subject">{{ $exam->subject->name }}</span>
                    </p>
                @elseif ($exam->subject)
                    <p class="mt-1" data-print-part="subject">{{ $exam->subject->name }}</p>
                @endif

                @if ($chapterNames !== [])
                    <p class="mt-1 text-[0.9em]" data-print-part="chapters">{{ __('Chapters') }}: {{ implode(', ', $chapterNames) }}</p>
                @endif

                <p class="mt-2 flex justify-between text-[0.9em]">
                    <span>{{ __('Time') }}: {{ __(':minutes minutes', ['minutes' => $digits((string) $exam->duration_minutes)]) }}</span>
                    <span>{{ __('Total marks') }}: {{ $digits(QuestionDisplay::marks($totalMarks)) }}</span>
                </p>

                @if ($showAnswers)
                    <p class="mt-2 text-[0.9em] font-semibold">{{ __('Answer copy — for the teacher') }}</p>
                @elseif ($isAnswerKey)
                    <p class="mt-2 text-[0.9em] font-semibold">{{ __('Answer key') }}</p>
                @endif
            </header>

            @if ($isAnswerKey)
                @include('teacher.partials.exam-answer-key', ['mcqQuestions' => $mcqQuestions])
            @else
                @if ($hasMcq)
                    <section class="mb-6">
                        @if ($hasCq)
                            <h2 class="mb-2 text-center text-[1.05em] font-bold">{{ __('MCQ questions') }}</h2>
                        @endif

                        <p class="qb-paper-instructions" data-print-part="instructions" data-print-instructions="mcq_instructions"></p>

                        <div class="qb-paper-columns">
                            {{-- A shared stimulus is printed once, above the questions it is for. --}}
                            @foreach (QuestionBlocks::blocks($mcqQuestions) as $block)
                                @if ($block['stimulus'])
                                    <x-question-stimulus :stimulus="$block['stimulus']" :heading="$block['heading']" />
                                @endif

                                @foreach ($block['questions'] as ['number' => $number, 'question' => $question])
                                    <div class="qb-paper-question">
                                        <span class="qb-paper-number">{{ $digits((string) $number) }}</span>
                                        <div class="min-w-0 flex-1">
                                            @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => $showAnswers, 'showStimulus' => false])
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($hasCq)
                    <section>
                        @if ($hasMcq)
                            <h2 class="mb-2 text-center text-[1.05em] font-bold">{{ __('CQ questions') }}</h2>
                        @endif

                        <p class="qb-paper-instructions" data-print-part="instructions" data-print-instructions="cq_instructions"></p>

                        {{-- CQ is always in one column. --}}
                        @foreach ($cqQuestions->values() as $index => $question)
                            <div class="qb-paper-question">
                                <span class="qb-paper-number">{{ $digits((string) ($index + 1)) }}</span>
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
    </div>

    <script type="application/json" id="qb-print-settings-data">
        {!! json_encode([
            'settings' => $printSettings,
            'saveUrl' => CurrentPanel::route('exams.print-settings'),
            'institutionNames' => $institutionNames,
            'defaultInstructions' => ['mcq_instructions' => $defaultInstructions['mcq'], 'cq_instructions' => $defaultInstructions['cq']],
            'fonts' => collect(PrintSettings::FONTS)->map(fn (array $font): string => $font['family']),
            'pageNumberStyle' => app()->getLocale() === 'bn' ? 'bengali' : 'decimal',
            'saved' => __('Saved'),
            'notSaved' => __('Not saved — check your connection'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}
    </script>

    <script>
        if (/FBAN|FBAV|FB_IAB|Instagram|Messenger/i.test(navigator.userAgent)) {
            document.getElementById('qb-in-app-browser-warning').hidden = false;
        }
    </script>
</body>
</html>
