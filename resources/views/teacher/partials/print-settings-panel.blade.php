{{--
    The print settings beside the question paper (a drawer on a phone).
    Every control names its setting (`data-setting`); print-settings.js
    applies a change to the paper at once and saves it for the user.
    What the settings are and their limits: App\Support\PrintSettings.

    @var array<string, mixed> $printSettings
    @var \Illuminate\Support\Collection<int, string> $institutionNames  Usable names, by id.
    @var \Illuminate\Support\Collection<int, string> $institutionLogos  Their logos' URLs, by id (only those with one).
    @var array{mcq: string, cq: string} $defaultInstructions
    @var bool $hasMcq
    @var bool $hasCq
    @var bool $isOmrSheet
    @var \App\Models\Exam $exam
    @var \Illuminate\Support\Collection $mcqQuestions  The paper's MCQ (the OMR sheet's questions).
--}}
@php
    use App\Enums\Locale;
    use App\Filament\Support\CurrentPanel;
    use App\Filament\Support\QuestionDisplay;
    use App\Support\OmrSheet;
    use App\Support\PrintSettings;

    $choice = fn (string $setting, mixed $value): array => [
        'data-setting' => $setting,
        'data-value' => $value,
        'aria-pressed' => $printSettings[$setting] == $value ? 'true' : 'false',
    ];
@endphp

<aside id="qb-print-settings" class="qb-no-print qb-print-settings" aria-label="{{ __('Print settings') }}">
    <div class="qb-print-settings-head">
        <h2 class="text-base font-bold">{{ __('Print settings') }}</h2>
        <span class="text-xs text-gray-500" data-save-status></span>
        <button type="button" class="qb-print-settings-close" data-settings-toggle aria-label="{{ __('Close') }}">✕</button>
    </div>

    {{-- Editing the paper's own words (print-editing.js) — not a saved setting. --}}
    <section class="qb-print-settings-group">
        <h3>{{ __('Edit the paper') }}</h3>

        <label class="qb-print-switch">
            <span>{{ __('Editing mode') }}</span>
            <input type="checkbox" role="switch" data-editing-toggle>
        </label>

        <p class="text-xs text-gray-500">{{ __('Click any text on the paper to change it. It changes only this printed paper, not the question bank, and is remembered in this browser.') }}</p>

        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-medium text-primary-700" data-edits-count data-template="{{ __(':count changes on this paper') }}"></span>
            <button type="button" hidden class="font-medium text-red-600 hover:underline" data-edits-reset data-confirm="{{ __('Undo every change made to this paper?') }}">
                {{ __('Undo all changes') }}
            </button>
        </div>
    </section>

    {{-- The header --}}
    <section class="qb-print-settings-group">
        <h3>{{ __('Paper header') }}</h3>

        <label class="qb-print-field">
            <span>{{ __('Institution name') }}</span>
            <select data-setting="institution_name_id" data-type="int">
                <option value="0">{{ __('Do not print') }}</option>
                @foreach ($institutionNames as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <a href="{{ CurrentPanel::route('pages.institution-names') }}" class="text-xs font-medium text-primary-600 hover:underline">
            {{ $institutionNames->isEmpty() ? __('Add your institution name') : __('Add or change names') }}
        </a>

        <label class="qb-print-field">
            <span>{{ __('Name size') }} <output data-output="institution_font_size"></output></span>
            <input type="range" min="14" max="48" data-setting="institution_font_size" data-type="int">
        </label>

        {{-- A logo is added to a name on the "Institution names" page and printed with it. --}}
        @if ($institutionLogos->isNotEmpty())
            <label class="qb-print-switch">
                <span>{{ __('Logo') }}</span>
                <input type="checkbox" role="switch" data-setting="show_logo" data-type="bool">
            </label>

            <div class="qb-print-field" data-shown-with="show_logo">
                <span>{{ __('Logo position') }}</span>
                <div class="qb-print-choices">
                    @foreach (['left' => __('Left'), 'above' => __('Above the name'), 'right' => __('Right side')] as $position => $label)
                        <button type="button" @foreach ($choice('logo_position', $position) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <label class="qb-print-field" data-shown-with="show_logo">
                <span>{{ __('Logo size') }} <output data-output="logo_size" data-unit="mm"></output></span>
                <input type="range" min="10" max="40" data-setting="logo_size" data-type="int">
            </label>
        @endif

        {{-- The OMR sheet's heading has no chapters, instructions or lines for the name. --}}
        @foreach (array_filter([
            'show_exam_title' => __('Exam name'),
            'show_class' => __('Class name'),
            'show_subject' => __('Subject name'),
            'show_chapters' => $isOmrSheet ? null : __('Chapter names'),
            'show_instructions' => $isOmrSheet ? null : __('Instructions'),
            'show_student_fields' => $isOmrSheet ? null : __('Lines for name and roll'),
        ]) as $setting => $label)
            <label class="qb-print-switch">
                <span>{{ $label }}</span>
                <input type="checkbox" role="switch" data-setting="{{ $setting }}" data-type="bool">
            </label>
        @endforeach

        @foreach ($isOmrSheet ? [] : array_filter(['mcq' => $hasMcq ? __('MCQ instructions') : null, 'cq' => $hasCq ? __('CQ instructions') : null]) as $type => $label)
            <label class="qb-print-field" data-shown-with="show_instructions">
                <span>{{ $label }}</span>
                <textarea rows="3" maxlength="1000" data-setting="{{ $type }}_instructions" data-type="text" data-default="{{ $defaultInstructions[$type] }}"></textarea>
                <button type="button" class="self-start text-xs font-medium text-primary-600 hover:underline" data-reset="{{ $type }}_instructions">
                    {{ __('Back to the default text') }}
                </button>
            </label>
        @endforeach
    </section>

    {{-- The OMR sheet's own settings: how many questions (saved on the exam), its paper, and the roll number's digits. --}}
    @if ($isOmrSheet)
        @php
            $omrSheet = new OmrSheet($exam, $mcqQuestions);
            $omrMcqCount = $omrSheet->entries()->count();
            $omrQuestionsError = session('errors')?->first('questions');
        @endphp
        <section class="qb-print-settings-group">
            <h3>{{ __('OMR sheet') }}</h3>

            <form method="POST" action="{{ CurrentPanel::route('exams.omr-questions', $exam) }}" class="qb-print-field">
                @csrf
                <label for="qb-omr-questions">{{ __('Questions on the sheet') }}</label>
                <div class="flex gap-2">
                    <input id="qb-omr-questions" type="number" name="questions" required
                           min="1" max="{{ max($omrMcqCount, OmrSheet::MAX_QUESTIONS) }}"
                           value="{{ $omrSheet->questionCount() }}" class="min-w-0 flex-1">
                    <button type="submit" class="qb-btn qb-btn--outline text-sm">{{ __('Apply') }}</button>
                </div>
                @if ($omrQuestionsError)
                    <span class="text-xs text-red-600">{{ $omrQuestionsError }}</span>
                @endif
            </form>
            <p class="text-xs text-gray-500">{{ __('The paper has :count MCQ. Fewer rows leave the last questions off the sheet (they count as unanswered); more rows stay blank and are not read. A sheet printed before the change can no longer be scanned.', ['count' => Locale::current()->writeDigits((string) $omrMcqCount)]) }}</p>

            <div class="qb-print-field">
                <span>{{ __('Paper size') }}</span>
                <div class="qb-print-choices">
                    @foreach (PrintSettings::OMR_PAPER_SIZES as $size)
                        <button type="button" @foreach ($choice('omr_paper_size', $size) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ $size }}</button>
                    @endforeach
                </div>
            </div>
            <p class="text-xs text-gray-500">{{ __('On A5 the same sheet is printed smaller. To get a PDF, use the print button above.') }}</p>

            <label class="qb-print-field">
                <span>{{ __('Digits in the roll number') }} <output data-output="omr_roll_digits" data-unit=""></output></span>
                <input type="range" min="0" max="{{ OmrSheet::ROLL_DIGITS }}" data-setting="omr_roll_digits" data-type="int">
            </label>
            <p class="text-xs text-gray-500">{{ __('0 = no roll number circles — then the roll is typed in when the sheet is scanned.') }}</p>
        </section>
    @endif

    {{-- The page (the OMR sheet keeps its own: above) --}}
    @unless ($isOmrSheet)
    <section class="qb-print-settings-group">
        <h3>{{ __('Page') }}</h3>

        <div class="qb-print-field">
            <span>{{ __('Paper size') }}</span>
            <div class="qb-print-choices">
                @foreach (PrintSettings::PAPER_SIZES as $size)
                    <button type="button" @foreach ($choice('paper_size', $size) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ $size }}</button>
                @endforeach
            </div>
        </div>

        <div class="qb-print-field">
            <span>{{ __('Margins (mm)') }}</span>
            <div class="grid grid-cols-4 gap-2">
                @foreach (['margin_top' => __('Top'), 'margin_right' => __('Right side'), 'margin_bottom' => __('Bottom'), 'margin_left' => __('Left')] as $setting => $label)
                    <label class="flex flex-col text-xs text-gray-500">
                        {{ $label }}
                        <input type="number" min="3" max="40" data-setting="{{ $setting }}" data-type="int">
                    </label>
                @endforeach
            </div>
        </div>

        @foreach (['header_text' => __('Text at the top of every page'), 'footer_text' => __('Text at the bottom of every page')] as $setting => $label)
            <label class="qb-print-field">
                <span>{{ $label }}</span>
                <input type="text" maxlength="{{ PrintSettings::RUNNING_TEXT_MAX }}" data-setting="{{ $setting }}" data-type="text">
            </label>
        @endforeach

        <label class="qb-print-switch">
            <span>{{ __('Page numbers') }}</span>
            <input type="checkbox" role="switch" data-setting="page_numbers" data-type="bool">
        </label>

        <div class="qb-print-field" data-shown-with="page_numbers">
            <span>{{ __('Page number position') }}</span>
            <div class="qb-print-choices">
                <button type="button" @foreach ($choice('page_number_position', 'center') as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ __('Middle') }}</button>
                <button type="button" @foreach ($choice('page_number_position', 'right') as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ __('Right side') }}</button>
            </div>
        </div>
    </section>
    @endunless

    {{-- The text --}}
    <section class="qb-print-settings-group">
        <h3>{{ __('Text') }}</h3>

        <label class="qb-print-field">
            <span>{{ __('Font') }}</span>
            <select data-setting="font" data-type="text">
                @foreach (PrintSettings::FONTS as $key => $font)
                    <option value="{{ $key }}" style="font-family: {{ $font['family'] }}">{{ $font['label'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="qb-print-field">
            <span>{{ __('Font size') }} <output data-output="font_size"></output></span>
            <input type="range" min="9" max="24" data-setting="font_size" data-type="int">
        </label>

        <div class="qb-print-field">
            <span>{{ __('Text alignment') }}</span>
            <div class="qb-print-choices">
                @foreach (['left' => __('Left'), 'center' => __('Middle'), 'right' => __('Right side'), 'justify' => __('Both sides')] as $align => $label)
                    <button type="button" @foreach ($choice('text_align', $align) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($hasMcq && ! $isOmrSheet)
            <div class="qb-print-field">
                <span>{{ __('Option style') }}</span>
                <div class="qb-print-choices">
                    {{-- Each sample is drawn by the same rule as the paper's options. --}}
                    @foreach (PrintSettings::OPTION_STYLES as $style)
                        <button type="button" @foreach ($choice('option_style', $style) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
                            <span class="qb-option-style" data-option-style="{{ $style }}"><span class="qb-question-option"><span class="qb-question-option-letter">{{ QuestionDisplay::letter(0) }}</span></span></span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="qb-print-field">
                <span>{{ __('Options in a row') }}</span>
                <div class="qb-print-choices">
                    @foreach ([1, 2, 4] as $count)
                        <button type="button" data-type="int" @foreach ($choice('options_per_row', $count) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>{{ Locale::current()->writeDigits((string) $count) }}</button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- The MCQ columns --}}
    @if ($hasMcq && ! $isOmrSheet)
        <section class="qb-print-settings-group">
            <h3>{{ __('Columns') }}</h3>
            @if ($hasCq)
                <p class="text-xs text-gray-500">{{ __('For the MCQ part — CQ is always printed in one column.') }}</p>
            @endif

            <div class="qb-print-choices">
                @foreach ([1, 2, 3] as $count)
                    <button type="button" data-type="int" @foreach ($choice('columns', $count) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach aria-label="{{ __(':count columns', ['count' => $count]) }}">
                        <span class="qb-print-column-icon">
                            @for ($column = 0; $column < $count; $column++)
                                <span></span>
                            @endfor
                        </span>
                    </button>
                @endforeach
            </div>

            <label class="qb-print-switch" data-shown-with="columns">
                <span>{{ __('Line between columns') }}</span>
                <input type="checkbox" role="switch" data-setting="column_divider" data-type="bool">
            </label>

            <label class="qb-print-field">
                <span>{{ __('Space below a question') }} <output data-output="question_gap"></output></span>
                <input type="range" min="0" max="40" data-setting="question_gap" data-type="int">
            </label>

            <label class="qb-print-field" data-shown-with="columns">
                <span>{{ __('Space between columns') }} <output data-output="column_gap"></output></span>
                <input type="range" min="0" max="60" data-setting="column_gap" data-type="int">
            </label>
        </section>
    @endif
</aside>
