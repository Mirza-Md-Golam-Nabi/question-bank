{{--
    The answer key alone: every MCQ's number on the left, the letter of its
    right option on the right — numbered exactly as on the question paper
    (QuestionBlocks), in a few columns side by side so even a long paper fits
    on one page, read top to bottom then on to the next column. A past paper
    may give several right options ("ক, গ") or none ("—").

    @var \Illuminate\Support\Collection $mcqQuestions
--}}
@php
    use App\Enums\Locale;
    use App\Filament\Support\QuestionBlocks;
    use App\Filament\Support\QuestionDisplay;

    $entries = QuestionBlocks::blocks($mcqQuestions)
        ->flatMap(fn (array $block): array => $block['questions'])
        ->map(fn (array $entry): array => [
            'number' => Locale::current()->writeDigits((string) $entry['number']),
            'answer' => collect($entry['question']->correctOptionIndexes())
                ->map(fn (int $index): string => QuestionDisplay::letter($index))
                ->implode(', '),
        ])
        ->values();

    $rowsPerColumn = (int) ceil($entries->count() / min(5, $entries->count()));
    $columns = $entries->chunk($rowsPerColumn)->map->values();
    $rows = collect(range(0, $rowsPerColumn - 1))
        ->map(fn (int $row) => $columns->map(fn ($column): ?array => $column->get($row)));
    $hasNoAnswer = $entries->contains(fn (array $entry): bool => $entry['answer'] === '');
@endphp

<div class="overflow-x-auto">
    <table class="qb-answer-key">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ __('No.') }}</th>
                    <th>{{ __('Answer') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $entry)
                        <td class="qb-answer-key-number">{{ $entry['number'] ?? '' }}</td>
                        <td class="qb-answer-key-answer">{{ $entry ? ($entry['answer'] !== '' ? $entry['answer'] : '—') : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($hasNoAnswer)
    <p class="mt-3 text-xs text-gray-600">{{ __('— : this question has no correct answer.') }}</p>
@endif
