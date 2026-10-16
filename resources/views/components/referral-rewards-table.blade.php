{{--
    A referrer's latest rewards, one row each — on a Teacher's or Student's
    "Referrals & commission" page and a Staff member's earnings page.
--}}
@props([
    'rewards',
    // Who paid: on the referrals page, where the referrer knows the buyer.
    'showBuyer' => false,
])

<x-data-table {{ $attributes }} :columns="array_filter([
    __('Date') => 'start',
    __('Student') => $showBuyer ? 'start' : null,
    __('Amount') => 'start',
    __('Status') => 'start',
    __('Available from') => 'start',
])">
    @foreach ($rewards as $reward)
        <tr>
            <td>{{ $reward->created_at->format('M j, Y') }}</td>
            @if ($showBuyer)
                <td>{{ $reward->payment?->user?->name ?? '—' }}</td>
            @endif
            <td class="font-medium">৳{{ number_format($reward->amount, 2) }}</td>
            <td>{{ $reward->status()->getLabel() }}</td>
            <td>{{ $reward->matures_at->format('M j, Y') }}</td>
        </tr>
    @endforeach
</x-data-table>
