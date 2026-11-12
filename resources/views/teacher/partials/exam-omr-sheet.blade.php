{{--
    The OMR answer sheet a student fills in by hand: their name, class and
    roll number (written, and its digits filled in on a grid), then every
    MCQ's number — numbered exactly as on the question paper
    (QuestionBlocks) — with a circle per option, in a few columns read top
    to bottom. It is for marking by hand or with the answer key laid over
    it; no machine reads it. The roll grid has ten columns and the print
    settings (`omr_roll_digits`, print-settings.js) show as many as asked.

    @var \Illuminate\Support\Collection $mcqQuestions
--}}
@php
    use App\Enums\Locale;
    use App\Filament\Support\QuestionBlocks;
    use App\Filament\Support\QuestionDisplay;

    $digits = fn (string $text): string => Locale::current()->writeDigits($text);

    $entries = QuestionBlocks::blocks($mcqQuestions)
        ->flatMap(fn (array $block): array => $block['questions'])
        ->map(fn (array $entry): array => [
            'number' => $digits((string) $entry['number']),
            'letters' => collect($entry['question']->options ?? [])->keys()->map(fn (int $index): string => QuestionDisplay::letter($index))->all(),
        ])
        ->values();

    // A short paper in one column; a long one in up to five, about 25 a column.
    $columnCount = $entries->count() <= 10 ? 1 : min(5, max(2, (int) ceil($entries->count() / 25)));
    $rowCount = (int) ceil($entries->count() / $columnCount);
@endphp

<section class="qb-omr">
    <div class="qb-omr-top">
        <div class="qb-omr-fields">
            @foreach ([__('Name'), __('Class'), __('Section'), __('Roll'), __('Signature')] as $field)
                <p><span>{{ $field }}:</span><span class="qb-omr-blank"></span></p>
            @endforeach
        </div>

        <div class="qb-omr-roll" data-omr-roll>
            <p class="qb-omr-roll-title">{{ __('Roll number') }}</p>
            <div class="qb-omr-roll-grid">
                @for ($column = 0; $column < 10; $column++)
                    <div class="qb-omr-roll-column" data-omr-roll-column="{{ $column }}">
                        <span class="qb-omr-roll-box"></span>
                        @for ($digit = 0; $digit <= 9; $digit++)
                            <span class="qb-omr-bubble">{{ $digits((string) $digit) }}</span>
                        @endfor
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <p class="qb-omr-instructions">{{ __('Fill the circle of the right answer completely with a ball-point pen. Fill only one circle for a question; do not cut or rub it out.') }}</p>

    <div class="qb-omr-answers" style="--qb-omr-columns: {{ $columnCount }}; --qb-omr-rows: {{ $rowCount }}">
        @foreach ($entries as $entry)
            {{-- A line under every fifth row of a column, to keep the place. --}}
            @php($row = $loop->index % $rowCount + 1)
            <div @class(['qb-omr-item', 'qb-omr-item--fifth' => $row % 5 === 0 && $row !== $rowCount])>
                <span class="qb-omr-number">{{ $entry['number'] }}</span>
                @foreach ($entry['letters'] as $letter)
                    <span class="qb-omr-bubble">{{ $letter }}</span>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
