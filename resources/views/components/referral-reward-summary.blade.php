@props([
    'summary',
    // Staff are paid their rewards in cash, so how much of it has been paid matters to them.
    'showPaid' => false,
])

{{-- What referring has earned a user so far (ReferralService::rewardSummaryFor) — on My Profile and the Staff's My Earnings. --}}
<dl {{ $attributes->class('flex flex-wrap gap-x-6 gap-y-1 text-sm') }}>
    <div class="flex gap-1.5">
        <dt class="text-gray-500 dark:text-gray-400">{{ __('Waiting for the refund period to end') }}:</dt>
        <dd class="font-bold">৳{{ number_format($summary['maturing'], 2) }}</dd>
    </div>
    <div class="flex gap-1.5">
        <dt class="text-gray-500 dark:text-gray-400">{{ __('Earned from referrals') }}:</dt>
        <dd class="font-bold">৳{{ number_format($summary['matured'], 2) }}</dd>
    </div>
    @if ($showPaid)
        <div class="flex gap-1.5">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Paid') }}:</dt>
            <dd class="font-bold">৳{{ number_format($summary['paid'], 2) }}</dd>
        </div>
    @endif
</dl>
