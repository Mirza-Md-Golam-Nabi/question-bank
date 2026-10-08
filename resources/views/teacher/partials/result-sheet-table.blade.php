@php
    /**
     * The ranked result table of an exam — the same markup on the Teacher's
     * results page and on the printable sheet (which also reads these cells
     * to draw the downloadable image).
     *
     * @var iterable<int, array<string, mixed>> $rows  Rows from ExamResultSheet::rowsFor().
     * @var string|null $viewAction  Name of the page action that opens one student's answers; adds an eye button per row (results page only).
     */
    $viewAction ??= null;
    use App\Filament\Support\QuestionDisplay;
    use App\Services\ExamResultSheet;
@endphp

<div class="qb-result-sheet-wrap">
    <table class="qb-result-sheet" data-qb-result-sheet>
        <thead>
            <tr>
                <th class="qb-result-sheet-num">{{ __('Position') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Phone/Email') }}</th>
                <th class="qb-result-sheet-num">{{ __('Marks') }}</th>
                <th class="qb-result-sheet-num">{{ __('Time taken') }}</th>

                @if ($viewAction)
                    <th class="qb-result-sheet-num"><span class="sr-only">{{ __('View answers') }}</span></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr @class(['qb-result-sheet-top' => $row['position'] <= 3])>
                    <td class="qb-result-sheet-num qb-result-sheet-position">{{ $row['position'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['contact'] ?: '—' }}</td>
                    <td class="qb-result-sheet-num">{{ QuestionDisplay::marks($row['score']) }}</td>
                    <td class="qb-result-sheet-num">{{ ExamResultSheet::formatDuration($row['duration_seconds']) }}</td>

                    @if ($viewAction)
                        <td class="qb-result-sheet-num">
                            {{-- The button is a flex box, which text-align doesn't move — centre it explicitly. --}}
                            <div class="qb-result-sheet-action">
                                <x-filament::icon-button
                                    icon="heroicon-o-eye"
                                    color="gray"
                                    :label="__('View answers')"
                                    :tooltip="__('View answers')"
                                    wire:click="mountAction('{{ $viewAction }}', { attempt: {{ $row['attempt']->id }} })"
                                />
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
