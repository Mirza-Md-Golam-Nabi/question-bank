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
    @vite(['resources/css/app.css', 'resources/js/katex-embed-renderer.js', 'resources/js/print-settings.js', 'resources/js/print-editing.js'])

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
            --qb-logo-size: 18mm;
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

        /* The institution's logo beside its name (or above it). */
        .qb-paper-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .qb-paper-brand[data-logo-position='above'] {
            flex-direction: column;
            gap: 0.25rem;
        }

        .qb-paper-brand[data-logo-position='right'] {
            flex-direction: row-reverse;
        }

        .qb-paper-logo {
            height: var(--qb-logo-size);
            width: auto;
            max-width: 40mm;
            object-fit: contain;
        }

        .qb-paper-student-fields {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
            text-align: start;
        }

        .qb-paper-student-fields span {
            flex: 1;
            border-bottom: 1px dotted #6b7280;
        }

        .qb-paper-student-fields span:first-child {
            flex: 2.5;
        }

        /* Every page's running header/footer: printed by @page; a preview of it on screen. */
        .qb-paper-running {
            margin: -0.5rem 0 0.5rem;
            border-bottom: 1px dashed #d1d5db;
            color: #6b7280;
            font-size: 10pt;
            text-align: center;
        }

        .qb-paper-running[data-print-running='footer_text'] {
            margin: 1rem 0 -0.5rem;
            border-top: 1px dashed #d1d5db;
            border-bottom: 0;
            text-align: start;
        }

        /*
         * The OMR answer sheet (teacher.partials.exam-omr-sheet): a frame of
         * fixed size in which everything sits at its own place in mm
         * (App\Support\OmrSheet) — sizes in mm too, so no print setting can
         * move what the scanner reads.
         */
        .qb-omr + .qb-omr {
            break-before: page;
        }

        .qb-omr-frame {
            /* The sheet's red and pink: the reader looks at the red channel only, where they are as light as the paper. */
            --qb-omr-red: #e23b3f;
            --qb-omr-pink: #fbdcdc;
            /* How much bigger the words are than on A4 — on A5 the frame is printed smaller, its words not as much. */
            --qb-omr-text: 1;

            position: relative;
            margin: 0 auto;
            break-inside: avoid;
            color: #111827;
            font-size: calc(3mm * var(--qb-omr-text));
            line-height: 1.2;
            text-align: start;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* On A5 the same frame, smaller (OmrSheet::A5_SCALE, set by print-settings.js). */
        .qb-paper[data-omr-scale] .qb-omr-frame {
            zoom: var(--qb-omr-scale);
            --qb-omr-text: 1.2;
        }

        .qb-omr-frame > *,
        .qb-omr-frame .qb-omr-grid > *,
        .qb-omr-frame .qb-omr-grid > div > * {
            position: absolute;
        }

        .qb-omr-frame .qb-omr-grid {
            inset: 0;
            pointer-events: none;
        }

        .qb-omr-frame .qb-omr-grid > div {
            position: static;
        }

        /*
         * The heading is the only part of the sheet the "Text" settings
         * change: its words follow the font size (--qb-omr-font, kept within
         * what fits the heading) and its lines the alignment (--qb-omr-align).
         * It never spills over the code cells above or the questions below.
         */
        .qb-omr-heading {
            --qb-omr-heading-text: calc(var(--qb-omr-text) * clamp(0.8, var(--qb-omr-font, 1), 1.15));

            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: var(--qb-omr-align, center);
            /* Too much to fit: the top (the institution's name) stays, the bottom is cut. */
            justify-content: safe center;
            gap: 1mm;
            text-align: var(--qb-text-align, center);
        }

        .qb-omr-heading .qb-paper-brand {
            justify-content: var(--qb-omr-align, center);
        }

        .qb-omr-heading .qb-paper-logo {
            max-height: 14mm;
        }

        .qb-omr-heading .qb-paper-institution {
            line-height: 1.1;
        }

        .qb-omr-exam {
            font-size: calc(3.6mm * var(--qb-omr-heading-text));
            font-weight: 600;
        }

        .qb-omr-exam [data-print-part]:not([hidden]) ~ [data-print-part]:not([hidden])::before {
            content: ' · ';
        }

        .qb-omr-title {
            font-size: calc(4.6mm * var(--qb-omr-heading-text));
            font-weight: 700;
        }

        .qb-omr-page {
            font-size: calc(3.2mm * var(--qb-omr-heading-text));
            font-weight: 600;
        }

        .qb-omr-warning {
            padding: 0.6mm 3mm;
            background: #111827;
            color: #fff;
            font-size: calc(3.1mm * var(--qb-omr-heading-text));
            font-weight: 600;
        }

        .qb-omr-pen {
            padding: 0.4mm 4mm;
            border: 0.35mm solid #111827;
            font-size: calc(3.3mm * var(--qb-omr-heading-text));
            font-weight: 600;
        }

        /* A question column: its head row, and a line between the numbers and the circles. */
        .qb-omr-column {
            display: grid;
            grid-template-columns: var(--qb-omr-number-width) 1fr;
            grid-template-rows: var(--qb-omr-head);
            border: 0.35mm solid #111827;
            background: linear-gradient(#111827, #111827) no-repeat var(--qb-omr-number-width) 0 / 0.3mm 100%;
        }

        .qb-omr-column-head {
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 0.3mm solid #111827;
            font-size: calc(3mm * var(--qb-omr-text));
            font-weight: 700;
            line-height: 1;
            text-align: center;
        }

        .qb-omr-column-head:first-child {
            font-size: calc(2.4mm * var(--qb-omr-text));
        }

        .qb-omr-stripe {
            background: var(--qb-omr-pink);
        }

        .qb-omr-grid-title {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0.3mm solid #111827;
            font-size: calc(2.7mm * var(--qb-omr-text));
            font-weight: 700;
            line-height: 1;
            text-align: center;
        }

        .qb-omr-grid-title--roll {
            width: calc(var(--qb-omr-roll-digits, {{ App\Support\OmrSheet::ROLL_DIGITS }}) * var(--qb-omr-pitch) + 0.3mm) !important;
        }

        .qb-omr-grid[data-omr-grid='set'] .qb-omr-grid-title {
            font-size: calc(2.1mm * var(--qb-omr-text));
        }

        /* A digit's column: a box to write it in, then its circles on pink. */
        .qb-omr-grid-strip {
            border: 0.3mm solid #111827;
            background: linear-gradient(#fff, #fff) no-repeat 0 0 / 100% var(--qb-omr-write), var(--qb-omr-pink);
        }

        .qb-omr-grid-strip::after {
            content: '';
            position: absolute;
            top: var(--qb-omr-write);
            right: 0;
            left: 0;
            border-top: 0.3mm solid #111827;
        }

        .qb-omr-box {
            border: 0.35mm solid #111827;
        }

        .qb-omr-box-label {
            font-size: calc(3mm * var(--qb-omr-text));
            font-weight: 600;
            text-align: center;
        }

        .qb-omr-box-label--before {
            display: flex;
            align-items: center;
            font-size: calc(3.4mm * var(--qb-omr-text));
            white-space: nowrap;
        }

        /* A solid square drawn by its borders, which print even with the browser's "background graphics" off. */
        .qb-omr-solid {
            width: 0;
            height: 0;
            border-style: solid;
            border-color: #000;
        }

        /* Red, letter and all: the reader does not see red, so an empty circle never reads as filled. */
        .qb-omr-bubble {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0.25mm solid var(--qb-omr-red);
            border-radius: 9999px;
            background: #fff;
            color: var(--qb-omr-red);
            line-height: 1;
        }

        .qb-omr-bubble--grid {
            font-size: calc(2.1mm * var(--qb-omr-text));
        }

        .qb-omr-number {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            line-height: 1;
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

        /* Editing mode (print-editing.js): what can be changed is outlined — on screen only. */
        @media screen {
            .qb-paper[data-editing] [contenteditable='true'] {
                outline: 1px dashed #93c5fd;
                outline-offset: 1px;
                border-radius: 2px;
                cursor: text;
            }

            .qb-paper[data-editing] [contenteditable='true']:hover,
            .qb-paper[data-editing] [contenteditable='true']:focus {
                outline: 2px solid #3b82f6;
                background: #eff6ff;
            }
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

        .qb-print-field :is(select, textarea, input[type='number'], input[type='text']) {
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

            /* @page prints the running header/footer on every page. */
            .qb-paper-running {
                display: none !important;
            }

            /* Printed in black; the tick alone marks the right answer. */
            .qb-question-option--correct {
                color: #000 !important;
            }
        }
    </style>
</head>
<body class="qb-print-page font-bangla bg-gray-100 min-h-screen">
    @include('teacher.partials.print-settings-panel', compact('exam', 'mcqQuestions', 'printSettings', 'institutionNames', 'defaultInstructions', 'hasMcq', 'hasCq', 'isOmrSheet', 'institutionLogos'))

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
                     answer key or OMR sheet for a CQ-only paper (CQ has no
                     answers to list or fill in). --}}
                @foreach ([
                    ['answers' => null, 'label' => __('Questions only'), 'hidden' => ! $showAnswers && ! $isAnswerKey && ! $isOmrSheet],
                    ['answers' => 1, 'label' => __('With answers'), 'hidden' => $showAnswers],
                    ['answers' => 'key', 'label' => __('Answer key only'), 'hidden' => $isAnswerKey || ! $hasMcq],
                    ['answers' => 'omr', 'label' => __('OMR sheet'), 'hidden' => $isOmrSheet || ! $hasMcq],
                ] as $copy)
                    @unless ($copy['hidden'])
                        <a href="{{ CurrentPanel::route('exams.print', array_filter(['exam' => $exam, 'answers' => $copy['answers']])) }}"
                           class="qb-btn qb-btn--outline text-sm">
                            {{ $copy['label'] }}
                        </a>
                    @endunless
                @endforeach

                @if ($isOmrSheet)
                    <a href="{{ CurrentPanel::route('exams.omr-scan', $exam) }}"
                       class="qb-btn qb-btn--primary text-sm">
                        {{ __('Scan filled-in sheets') }}
                    </a>
                @endif

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
            {{-- The OMR sheet has its own heading inside its frame (teacher.partials.exam-omr-sheet). --}}
            @unless ($isOmrSheet)
            <p class="qb-paper-running" data-print-running="header_text" hidden></p>

            <header class="mb-4 border-b border-gray-300 pb-3 text-center">
                <div class="qb-paper-brand" data-print-brand>
                    <img class="qb-paper-logo" data-print-logo alt="" hidden>
                    <p class="qb-paper-institution" data-print-institution hidden></p>
                </div>

                <h1 class="text-[1.25em] font-bold" data-print-part="exam_title" data-editable="title">{{ $exam->title }}</h1>

                @if ($exam->classSubject)
                    <p class="mt-1">
                        <span data-print-part="class" data-editable="class">{{ $exam->classSubject->academicClass->name }}</span>
                        <span data-print-part="subject" data-editable="subject">{{ $exam->subject->name }}</span>
                    </p>
                @elseif ($exam->subject)
                    <p class="mt-1" data-print-part="subject" data-editable="subject">{{ $exam->subject->name }}</p>
                @endif

                @if ($chapterNames !== [])
                    <p class="mt-1 text-[0.9em]" data-print-part="chapters" data-editable="chapters">{{ __('Chapters') }}: {{ implode(', ', $chapterNames) }}</p>
                @endif

                <p class="mt-2 flex justify-between text-[0.9em]">
                    <span data-editable="time">{{ __('Time') }}: {{ __(':minutes minutes', ['minutes' => $digits((string) $exam->duration_minutes)]) }}</span>
                    <span data-editable="marks">{{ __('Total marks') }}: {{ $digits(QuestionDisplay::marks($totalMarks)) }}</span>
                </p>

                @if ($showAnswers)
                    <p class="mt-2 text-[0.9em] font-semibold">{{ __('Answer copy — for the teacher') }}</p>
                @elseif ($isAnswerKey)
                    <p class="mt-2 text-[0.9em] font-semibold">{{ __('Answer key') }}</p>
                @else
                    {{-- Where the student writes who they are, on the question paper itself. --}}
                    <p class="qb-paper-student-fields text-[0.9em]" data-print-part="student_fields">
                        <span>{{ __('Name') }}:</span>
                        <span>{{ __('Roll') }}:</span>
                        <span>{{ __('Section') }}:</span>
                    </p>
                @endif
            </header>
            @endunless

            @if ($isAnswerKey)
                @include('teacher.partials.exam-answer-key', ['mcqQuestions' => $mcqQuestions])
            @elseif ($isOmrSheet)
                @include('teacher.partials.exam-omr-sheet', ['exam' => $exam, 'mcqQuestions' => $mcqQuestions])
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
                                    <div class="qb-paper-question" data-edit-scope="{{ class_basename($question) }}-{{ $question->id }}">
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
                            <div class="qb-paper-question" data-edit-scope="{{ class_basename($question) }}-{{ $question->id }}">
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

            @unless ($isOmrSheet)
                <p class="qb-paper-running" data-print-running="footer_text" hidden></p>
            @endunless
        </main>
    </div>

    <script type="application/json" id="qb-print-settings-data">
        {!! json_encode([
            'settings' => $printSettings,
            'editsKey' => 'qb-print-edits:'.$exam->id,
            'saveUrl' => CurrentPanel::route('exams.print-settings'),
            'institutionNames' => $institutionNames,
            'institutionLogos' => $institutionLogos,
            'defaultInstructions' => ['mcq_instructions' => $defaultInstructions['mcq'], 'cq_instructions' => $defaultInstructions['cq']],
            'fonts' => collect(PrintSettings::FONTS)->map(fn (array $font): string => $font['family']),
            'pageNumberStyle' => app()->getLocale() === 'bn' ? 'bengali' : 'decimal',
            'omrFrame' => $isOmrSheet ? ['width' => App\Support\OmrSheet::WIDTH, 'height' => App\Support\OmrSheet::HEIGHT, 'a5Scale' => App\Support\OmrSheet::A5_SCALE] : null,
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
