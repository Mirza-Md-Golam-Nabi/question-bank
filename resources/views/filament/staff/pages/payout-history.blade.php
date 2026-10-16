<x-filament-panels::page>
    <x-filament::section>
        <x-data-table :columns="[__('Month') => 'start', __('Paid on') => 'start', __('Amount') => 'center', __('Reference note') => 'start', __('Paid by') => 'start']">
                    @forelse ($this->payouts() as $payout)
                        <tr>
                            <td>{{ $payout->paid_at?->format('F Y') }}</td>
                            <td>{{ $payout->paid_at?->format('d M, Y') }}</td>
                            <td class="text-center font-semibold">৳{{ number_format($payout->total_amount, 2) }}</td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $payout->reference_note ?: '—' }}</td>
                            <td>{{ $payout->paidBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">{{ __('No payouts yet.') }}</td>
                        </tr>
                    @endforelse
        </x-data-table>
    </x-filament::section>
</x-filament-panels::page>
