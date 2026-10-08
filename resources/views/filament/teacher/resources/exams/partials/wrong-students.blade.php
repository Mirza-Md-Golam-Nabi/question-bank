@php
    /**
     * The students who answered one question wrongly, and what each chose.
     *
     * @var \Illuminate\Support\Collection<int, array{position: int, name: string, contact: string|null, answer: string|null}> $students
     */
@endphp

@if ($students->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Nobody answered this question wrongly.') }}</p>
@else
    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ trans_choice(':count student|:count students', $students->count()) }}</p>

    <div class="qb-result-sheet-wrap">
        <table class="qb-result-sheet">
            <thead>
                <tr>
                    <th class="qb-result-sheet-num">{{ __('Position') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Phone/Email') }}</th>
                    <th>{{ __('Their answer') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    <tr>
                        <td class="qb-result-sheet-num">{{ $student['position'] }}</td>
                        <td>{{ $student['name'] }}</td>
                        <td>{{ $student['contact'] ?: '—' }}</td>
                        <td class="qb-question-text">{!! $student['answer'] !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
