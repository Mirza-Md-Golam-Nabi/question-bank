{{--
    The OMR answer sheet a student fills in by hand — and the teacher's phone
    reads (CLAUDE.md rule 10.11). Everything inside the frame sits exactly
    where App\Support\OmrSheet says, in millimetres: the four corner squares
    and the row of code cells between the top two are what the scanner finds
    the sheet and tells the exam and page by. Below the heading, every MCQ
    (numbered as on the question paper) in columns with a circle per option,
    then blank rows up to the number of questions the teacher chose;
    on the right the number grids — only the roll number's is read, and it
    shows as many columns as the print settings' `omr_roll_digits`
    (print-settings.js) — and the boxes written in by hand. Circles, their
    letters and the stripes are red/pink, which the reader does not see.
    A paper too long for one sheet goes on to the next. On A5 the whole
    frame is printed smaller (OmrSheet::A5_SCALE, print-settings.js), its
    words not as much (--qb-omr-text). Each part — the answer list with
    every other word, and each number grid — is printed in its own language
    from the print settings (PrintSettings::OMR_LANGUAGES): words, letters
    and numbers, whatever language the panel is in.

    @var \App\Models\Exam $exam
    @var \Illuminate\Support\Collection $mcqQuestions
    @var array<string, string> $languages  part => 'bn' or 'en' (PrintSettings::omrLanguages())
--}}
@php
    use App\Enums\Locale;
    use App\Filament\Support\QuestionDisplay;
    use App\Support\OmrSheet;

    $panelLocale = app()->getLocale();
    $language = $languages['sheet'];
    app()->setLocale($language);

    $digits = fn (string $text): string => Locale::current()->writeDigits($text);
    $mm = fn (float $value): string => round($value, 2).'mm';
    $pages = (new OmrSheet($exam, $mcqQuestions))->pages();
    $half = OmrSheet::MARKER_SIZE / 2;
    $gridLabel = fn (array $grid, string $gridLanguage, int $row): string => $grid['labels'] === 'letters'
        ? QuestionDisplay::letter($row, $gridLanguage)
        : Locale::from($gridLanguage)->writeDigits((string) $row);
@endphp

@foreach ($pages as $page)
    <section class="qb-omr" data-omr-sheet lang="{{ $language }}">
        <div class="qb-omr-frame" style="width: {{ $mm(OmrSheet::WIDTH) }}; height: {{ $mm(OmrSheet::HEIGHT) }}">
            @foreach (OmrSheet::markers() as [$x, $y])
                <span class="qb-omr-solid" style="left: {{ $mm($x - $half) }}; top: {{ $mm($y - $half) }}; border-width: {{ $mm($half) }}"></span>
            @endforeach

            @foreach (OmrSheet::codeCells() as $bit => [$x, $y])
                @if ($page['code'][$bit] === '1')
                    <span class="qb-omr-solid" style="left: {{ $mm($x - OmrSheet::CODE_CELL / 2) }}; top: {{ $mm($y - OmrSheet::CODE_CELL / 2) }}; border-width: {{ $mm(OmrSheet::CODE_CELL / 2) }}"></span>
                @endif
            @endforeach

            <header class="qb-omr-heading" style="left: 10mm; top: {{ $mm(OmrSheet::HEADING_TOP) }}; width: {{ $mm(OmrSheet::WIDTH - 20) }}; height: {{ $mm(OmrSheet::HEADING_HEIGHT) }}">
                <div class="qb-paper-brand" data-print-brand>
                    <img class="qb-paper-logo" data-print-logo alt="" hidden>
                    <p class="qb-paper-institution" data-print-institution hidden></p>
                </div>

                <p class="qb-omr-exam">
                    <span data-print-part="exam_title">{{ $exam->title }}</span>
                    @if ($exam->classSubject)
                        <span data-print-part="class">{{ $exam->classSubject->academicClass->name }}</span>
                    @endif
                    @if ($exam->subject)
                        <span data-print-part="subject">{{ $exam->subject->display_name }}</span>
                    @endif
                </p>

                <p class="qb-omr-title">
                    {{ __('Answer sheet of the objective test') }}
                    @if (count($pages) > 1)
                        <span class="qb-omr-page">({{ __('Sheet :page of :pages', ['page' => $digits((string) ($page['index'] + 1)), 'pages' => $digits((string) count($pages))]) }})</span>
                    @endif
                </p>

                <p class="qb-omr-warning">{{ __('Do not make any mark or write anything on the answer sheet except in the places given for it') }}</p>
                <p class="qb-omr-pen">{{ __('Must be filled in with a ball-point pen') }}</p>
            </header>

            {{-- The question columns: a head row, then a row per question. --}}
            @foreach ($page['columns'] as $column)
                <div class="qb-omr-column" style="left: {{ $mm($column['x']) }}; top: {{ $mm(OmrSheet::BODY_TOP) }}; width: {{ $mm($column['width']) }}; height: {{ $mm(OmrSheet::COLUMN_HEAD + $column['rows'] * $page['rowPitch'] + 0.6) }}; --qb-omr-number-width: {{ $mm(OmrSheet::NUMBER_WIDTH) }}; --qb-omr-head: {{ $mm(OmrSheet::COLUMN_HEAD) }}">
                    <span class="qb-omr-column-head">{{ __('Q. no.') }}</span>
                    <span class="qb-omr-column-head">{{ __('Answer list') }}</span>
                </div>
            @endforeach

            @foreach ($page['questions'] as $question)
                @php
                    $column = $page['columns'][intdiv($loop->index, $page['columns'][0]['rows'])];
                    $stripe = min($page['rowPitch'] - 1, $page['bubble'] + 1.4);
                @endphp
                <span class="qb-omr-stripe" style="left: {{ $mm($column['x'] + OmrSheet::NUMBER_WIDTH + 0.6) }}; top: {{ $mm($question['y'] - $stripe / 2) }}; width: {{ $mm($column['width'] - OmrSheet::NUMBER_WIDTH - 1.2) }}; height: {{ $mm($stripe) }}"></span>
                <span class="qb-omr-number" style="left: {{ $mm($question['x']) }}; top: {{ $mm($question['y'] - $page['rowPitch'] / 2) }}; width: {{ $mm(OmrSheet::NUMBER_WIDTH) }}; height: {{ $mm($page['rowPitch']) }}; font-size: calc({{ $mm(min(3.6, $page['rowPitch'] * 0.55)) }} * var(--qb-omr-text))">{{ $digits((string) $question['number']) }}</span>
                @foreach ($question['options'] as $option => [$x, $y])
                    <span class="qb-omr-bubble"
                          style="left: {{ $mm($x - $page['bubble'] / 2) }}; top: {{ $mm($y - $page['bubble'] / 2) }}; width: {{ $mm($page['bubble']) }}; height: {{ $mm($page['bubble']) }}; font-size: calc({{ $mm($page['bubble'] * 0.55) }} * var(--qb-omr-text))">{{ QuestionDisplay::letter($option) }}</span>
                @endforeach
            @endforeach

            {{-- The number grids: a column per digit, a box on top to write it in. --}}
            @foreach (OmrSheet::grids() as $key => $grid)
                @php $gridLanguage = $languages[$key]; @endphp
                <div class="qb-omr-grid" data-omr-grid="{{ $key }}" lang="{{ $gridLanguage }}" @if ($key === 'roll') data-omr-roll @endif>
                    <span class="qb-omr-grid-title @if ($key === 'roll') qb-omr-grid-title--roll @endif"
                          style="left: {{ $mm($grid['x']) }}; top: {{ $mm($grid['y']) }}; width: {{ $mm($grid['columns'] * $grid['pitch'] + 0.3) }}; height: {{ $mm(OmrSheet::GRID_TITLE + 0.3) }}; --qb-omr-pitch: {{ $mm($grid['pitch']) }}">{{ __($grid['title'], [], $gridLanguage) }}</span>

                    @foreach (OmrSheet::gridColumns($grid) as $index => $circles)
                        <div @if ($key === 'roll') data-omr-roll-column="{{ $index }}" @endif>
                            <span class="qb-omr-grid-strip" style="left: {{ $mm($grid['x'] + $index * $grid['pitch']) }}; top: {{ $mm($grid['y'] + OmrSheet::GRID_TITLE) }}; width: {{ $mm($grid['pitch'] + 0.3) }}; height: {{ $mm(OmrSheet::gridHeight($grid) - OmrSheet::GRID_TITLE) }}; --qb-omr-write: {{ $mm(OmrSheet::GRID_WRITE_ROW) }}"></span>
                            @foreach ($circles as $row => [$x, $y])
                                <span class="qb-omr-bubble qb-omr-bubble--grid"
                                      style="left: {{ $mm($x - OmrSheet::GRID_BUBBLE / 2) }}; top: {{ $mm($y - OmrSheet::GRID_BUBBLE / 2) }}; width: {{ $mm(OmrSheet::GRID_BUBBLE) }}; height: {{ $mm(OmrSheet::GRID_BUBBLE) }}">{{ $gridLabel($grid, $gridLanguage, $row) }}</span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach

            @foreach (OmrSheet::boxes() as $box)
                <span class="qb-omr-box" style="left: {{ $mm($box['x']) }}; top: {{ $mm($box['y']) }}; width: {{ $mm($box['width']) }}; height: {{ $mm($box['height']) }}"></span>
                @if ($box['labelBefore'])
                    <span class="qb-omr-box-label qb-omr-box-label--before" style="right: {{ $mm(OmrSheet::WIDTH - $box['x'] + 1.5) }}; top: {{ $mm($box['y']) }}; height: {{ $mm($box['height']) }}">{{ __($box['label']) }} :</span>
                @else
                    <span class="qb-omr-box-label" style="left: {{ $mm($box['x']) }}; top: {{ $mm($box['y'] + $box['height'] + 0.8) }}; width: {{ $mm($box['width']) }}">{{ __($box['label']) }}</span>
                @endif
            @endforeach
        </div>
    </section>
@endforeach

@php app()->setLocale($panelLocale); @endphp
