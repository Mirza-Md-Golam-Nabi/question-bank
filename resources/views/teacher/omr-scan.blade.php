@php
    /**
     * Scanning the students' filled-in OMR sheets (CLAUDE.md rule 10.11) —
     * the camera, the sheets read so far to check and save, all run by
     * resources/js/omr-scan.js; the reading itself is omr-reader.js.
     *
     * @var \App\Models\Exam $exam
     * @var array<string, mixed> $layout  App\Support\OmrSheet::scannerLayout()
     * @var list<array{id: int, name: string, sections: list<array{id: int, name: string}>}>|null $classes  A School's classes; null for a teacher's exam.
     */
    use App\Filament\Support\QuestionDisplay;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Scan OMR sheets') }} — {{ $exam->title }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/omr-scan.js'])

    <style>
        .qb-omr-camera {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            background: #111827;
            aspect-ratio: 3 / 4;
            max-height: 70vh;
            margin-inline: auto;
        }

        .qb-omr-camera video,
        .qb-omr-camera canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .qb-omr-camera[data-flash] {
            box-shadow: inset 0 0 0 6px #10b981;
        }

        .qb-omr-card[data-saved] {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .qb-omr-preview {
            width: 100%;
            max-width: 26rem;
            height: auto;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }

        .qb-omr-choice {
            min-width: 2rem;
            padding: 0.125rem 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: #fff;
            font-size: 0.875rem;
        }

        .qb-omr-choice[aria-pressed='true'] {
            border-color: #2563eb;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 600;
        }

        .qb-omr-flag--unclear {
            background: #fef3c7;
        }

        .qb-omr-flag--several {
            background: #fee2e2;
        }
    </style>
</head>
<body class="font-bangla bg-gray-100 min-h-screen">
    <div class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
            <h1 class="me-auto text-base font-bold text-gray-900">{{ __('Scan OMR sheets') }} — {{ $exam->title }}</h1>
            <a href="{{ $printUrl }}" target="_blank" class="qb-btn qb-btn--outline text-sm">{{ __('Print OMR sheet') }}</a>
            <a href="{{ $resultsUrl }}" class="qb-btn qb-btn--outline text-sm">{{ __('Results') }}</a>
            <a href="{{ $examsUrl }}" class="text-sm font-medium text-gray-600 hover:underline">{{ __('Back to exams') }}</a>
        </div>
    </div>

    <main class="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-4">
        @if ($rosterError)
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ __('The school software could not be reached, so its classes are not available: :error', ['error' => $rosterError]) }}</p>
        @endif

        <section class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-600">
                {{ __('Hold the phone over one filled-in sheet at a time, with all four black corner squares in view. A sheet is read on its own (you hear a beep); then put the next one under the camera. Check each sheet below and save it.') }}
            </p>

            @if ($classes !== null)
                <div class="grid gap-2 sm:grid-cols-2" data-school-class>
                    <label class="flex flex-col gap-1 text-sm font-medium">
                        {{ __('Class') }}
                        <select data-class class="rounded-lg border border-gray-300 px-2 py-1.5">
                            <option value="">{{ __('Choose…') }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium" data-section-field hidden>
                        {{ __('Section') }}
                        <select data-section class="rounded-lg border border-gray-300 px-2 py-1.5"></select>
                    </label>
                    <p class="text-xs text-gray-500 sm:col-span-2">{{ __('Scan one class and section at a time — the sheets are saved under the class and section chosen here.') }}</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                <button type="button" data-camera-toggle class="qb-btn qb-btn--primary text-sm">{{ __('Start camera') }}</button>
                <label class="qb-btn qb-btn--outline cursor-pointer text-sm">
                    {{ __('Choose photos / scans') }}
                    <input type="file" accept="image/*" multiple data-files class="sr-only">
                </label>
            </div>

            <div class="qb-omr-camera w-full" data-camera hidden>
                <video data-video playsinline muted></video>
                <canvas data-overlay></canvas>
            </div>

            <p class="text-sm font-medium text-gray-700" data-status aria-live="polite"></p>
        </section>

        <section class="flex flex-col gap-3" data-sheets-section hidden>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="me-auto text-base font-bold text-gray-900">{{ __('Sheets read') }} <span data-count></span></h2>
                <button type="button" data-save-all class="qb-btn qb-btn--success text-sm">{{ __('Save all that are ready') }}</button>
            </div>
            <div class="flex flex-col gap-3" data-sheets></div>
        </section>
    </main>

    <script type="application/json" id="qb-omr-scan-data">
        {!! json_encode([
            'layout' => $layout,
            'saveUrl' => $saveUrl,
            'classes' => $classes,
            'letters' => array_map(fn (int $index): string => QuestionDisplay::letter($index), range(0, 5)),
            'banglaDigits' => app()->getLocale() === 'bn',
            'text' => [
                'searching' => __('Looking for a sheet…'),
                'notThisExam' => __('Hold the sheet flat with all four corner squares in view. If it still is not read, it may be a sheet of another exam, or printed before the exam changed.'),
                'read' => __('Read: roll :roll'),
                'alreadyRead' => __('This sheet is already in the list.'),
                'cameraFailed' => __('The camera could not be started. Allow the camera for this site, or choose photos instead.'),
                'stopCamera' => __('Stop camera'),
                'startCamera' => __('Start camera'),
                'noSheetInPhoto' => __('No sheet of this exam was found in :file.'),
                'chooseClass' => __('Choose the class (and section) first.'),
                'roll' => __('Roll'),
                'name' => __('Name (optional)'),
                'save' => __('Save'),
                'saving' => __('Saving…'),
                'remove' => __('Remove'),
                'saved' => __('Saved — :score of :total marks'),
                'question' => __('Question :number'),
                'blank' => __('Blank'),
                'several' => __('More than one'),
                'unclear' => __('Not clear — choose what the student meant'),
                'severalNote' => __('More than one circle filled — counted as wrong unless you choose one'),
                'rollUnclear' => __('The roll could not be read clearly — check it.'),
                'needsRoll' => __('Write the roll number.'),
                'needsCheck' => __(':count questions to check'),
                'allGood' => __('Everything was read clearly.'),
                'showAll' => __('Show all answers'),
                'hideAll' => __('Hide all answers'),
                'missingPages' => __('Sheets still to scan for this roll: :pages'),
                'failed' => __('Could not save — check your connection and try again.'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}
    </script>
</body>
</html>
