@php
    /**
     * The students behind one of a question's counts: those who answered it
     * wrongly (each with what they chose) or those who left it blank (no
     * answer to show, so no "Their answer" column).
     *
     * @var \Illuminate\Support\Collection<int, array{position: int, name: string, contact: string|null, answer?: string|null}> $students
     * @var string $emptyMessage
     * @var bool $showAnswer
     */
@endphp

@if ($students->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
@else
    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ trans_choice(':count student|:count students', $students->count()) }}</p>

    <div class="qb-result-sheet-wrap">
        <table class="qb-result-sheet">
            <thead>
                <tr>
                    <th class="qb-result-sheet-num">{{ __('Position') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Phone/Email') }}</th>
                    @if ($showAnswer)
                        <th>{{ __('Their answer') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    <tr>
                        <td class="qb-result-sheet-num">{{ $student['position'] }}</td>
                        <td>{{ $student['name'] }}</td>
                        <td>{{ $student['contact'] ?: '—' }}</td>
                        @if ($showAnswer)
                            <td class="qb-question-text">{!! $student['answer'] !!}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
