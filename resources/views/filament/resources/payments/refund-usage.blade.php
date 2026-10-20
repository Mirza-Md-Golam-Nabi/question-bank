@php
    /**
     * In the Admin's refund window: what has been done with the
     * subscription so far, and — when anything has — that its money can't
     * come back.
     *
     * @var array{teacher_exams: int, self_practice: int, past_papers: int} $usage
     * @var bool $used
     */
@endphp

<div class="space-y-3">
    <x-data-table :columns="[__('Used on this subscription so far') => 'start', __('Count') => 'center']" compact>
        <tr>
            <td>{{ __('Exams made') }}</td>
            <td class="text-center">{{ $usage['teacher_exams'] }}</td>
        </tr>
        <tr>
            <td>{{ __('Self-practice exams') }}</td>
            <td class="text-center">{{ $usage['self_practice'] }}</td>
        </tr>
        <tr>
            <td>{{ __('Past papers') }}</td>
            <td class="text-center">{{ $usage['past_papers'] }}</td>
        </tr>
    </x-data-table>

    @if ($used)
        <p class="text-sm font-medium text-danger-600 dark:text-danger-400">
            {{ __('This subscription has been used, so it cannot be refunded.') }}
        </p>
    @endif
</div>
