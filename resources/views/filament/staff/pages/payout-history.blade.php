<x-filament-panels::page>
    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr>
                        <th class="p-2 text-start">Month</th>
                        <th class="p-2 text-start">Paid on</th>
                        <th class="p-2 text-center">Amount</th>
                        <th class="p-2 text-start">Reference note</th>
                        <th class="p-2 text-start">Paid by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->payouts() as $payout)
                        <tr>
                            <td class="p-2">{{ $payout->paid_at?->format('F Y') }}</td>
                            <td class="p-2">{{ $payout->paid_at?->format('d M, Y') }}</td>
                            <td class="p-2 text-center font-semibold">৳{{ number_format($payout->total_amount, 2) }}</td>
                            <td class="p-2 text-gray-500 dark:text-gray-400">{{ $payout->reference_note ?: '—' }}</td>
                            <td class="p-2">{{ $payout->paidBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-2" colspan="5">No payouts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
