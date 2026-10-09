@php
    use App\Filament\Support\QuestionDisplay;
    use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Results') }} — {{ $exam->title }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/result-sheet-image.js'])

    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        @media print {
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
        }
    </style>
</head>
<body class="font-bangla bg-gray-100 min-h-screen">
    <div class="qb-no-print sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()" class="qb-btn qb-btn--primary text-sm">
                {{ __('Print / Save as PDF') }}
            </button>

            <button type="button" class="qb-btn qb-btn--outline text-sm" data-qb-download-image>
                {{ __('Download as image') }}
            </button>

            <a href="{{ ExamResults::getUrl(['record' => $exam], panel: 'teacher') }}" class="qb-btn qb-btn--outline ms-auto text-sm">
                {{ __('Back to results') }}
            </a>
        </div>

        <p class="mx-auto mt-2 max-w-3xl text-xs text-gray-500">
            {{ __('To get a PDF: press the button, then choose "Save as PDF" as the printer (on iPhone: Share → Save to Files).') }}
        </p>
    </div>

    {{-- data-qb-result-sheet-* is what the image download reads its header from. --}}
    <main
        class="qb-paper mx-auto my-6 max-w-3xl bg-white p-6 shadow sm:p-10"
        data-qb-result-sheet-root
        data-file-name="{{ \Illuminate\Support\Str::slug($exam->title) ?: 'results' }}"
    >
        <x-exam-heading :exam="$exam" class="mb-3" data-qb-result-sheet-heading />

        <p class="mb-4 flex flex-wrap justify-between gap-x-6 gap-y-1 border-y border-gray-300 py-2 text-sm" data-qb-result-sheet-summary>
            <span>{{ __('Total participants') }}: <strong>{{ $rows->count() }}</strong></span>
            <span>{{ __('Total marks') }}: <strong>{{ QuestionDisplay::marks($exam->total_marks) }}</strong></span>
        </p>

        @if ($rows->isEmpty())
            <p class="text-center text-gray-500">{{ __('Nobody has submitted this exam yet') }}</p>
        @else
            @include('teacher.partials.result-sheet-table', ['rows' => $rows])
        @endif
    </main>
</body>
</html>
