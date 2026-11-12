{{--
    The print settings beside the question paper (a drawer on a phone).
    Every control names its setting (`data-setting`); print-settings.js
    applies a change to the paper at once and saves it for the user.
    What the settings are and their limits: App\Support\PrintSettings.

    @var array<string, mixed> $printSettings
    @var \Illuminate\Support\Collection<int, string> $institutionNames  Usable names, by id.
    @var array{mcq: string, cq: string} $defaultInstructions
    @var bool $hasMcq
    @var bool $hasCq
--}}
@php
    use App\Enums\Locale;
    use App\Filament\Support\CurrentPanel;
    use App\Filament\Support\QuestionDisplay;
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

        @foreach ([
            'show_exam_title' => __('Exam name'),
            'show_class' => __('Class name'),
            'show_subject' => __('Subject name'),
            'show_chapters' => __('Chapter names'),
            'show_instructions' => __('Instructions'),
        ] as $setting => $label)
            <label class="qb-print-switch">
                <span>{{ $label }}</span>
                <input type="checkbox" role="switch" data-setting="{{ $setting }}" data-type="bool">
            </label>
        @endforeach

        @foreach (array_filter(['mcq' => $hasMcq ? __('MCQ instructions') : null, 'cq' => $hasCq ? __('CQ instructions') : null]) as $type => $label)
            <label class="qb-print-field" data-shown-with="show_instructions">
                <span>{{ $label }}</span>
                <textarea rows="3" maxlength="1000" data-setting="{{ $type }}_instructions" data-type="text" data-default="{{ $defaultInstructions[$type] }}"></textarea>
                <button type="button" class="self-start text-xs font-medium text-primary-600 hover:underline" data-reset="{{ $type }}_instructions">
                    {{ __('Back to the default text') }}
                </button>
            </label>
        @endforeach
    </section>

    {{-- The page --}}
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

        @if ($hasMcq)
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
    @if ($hasMcq)
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
