<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $rewards = $this->rewards();
        $withdrawals = $this->withdrawals();
        $walletEntries = $this->walletEntries();
    @endphp

    @unless ($this->earnsCommission())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" :heading="__('Commission is paused')">
            <p class="text-sm">{{ __('You earn commission only while you have a paid subscription (not during the free trial). Purchases your students make now earn you nothing.') }}</p>

            <x-filament::button tag="a" :href="$this->subscriptionUrl()" class="mt-3" size="sm">
                {{ __('See plans') }}
            </x-filament::button>
        </x-filament::section>
    @endunless

    <x-filament::section :heading="__('My commission')" icon="heroicon-o-banknotes">
        <p class="text-sm">
            @if ($this->isTeacher())
                {{ __('You earn :percent% of what a student under your referral pays. Share your exam links, or your code :code.', [
                    'percent' => $this->commissionPercent(),
                    'code' => $this->referralCode(),
                ]) }}
            @else
                {{ __('You earn :percent% of what a student under your referral pays. Share your code :code with your friends.', [
                    'percent' => $this->commissionPercent(),
                    'code' => $this->referralCode(),
                ]) }}
            @endif
        </p>

        <x-referral-reward-summary :summary="$this->rewardSummary()" class="mt-3" />

        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ([
                __('In your wallet') => $summary['balance'],
                __('Always kept for your next subscription') => $summary['reserve'],
                __('You can take out now') => $summary['withdrawable'],
            ] as $label => $amount)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="text-xl font-bold">৳{{ number_format($amount, 2) }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            {{ $this->withdrawAction }}

            @if ($summary['pending'])
                <span class="text-sm text-warning-600 dark:text-warning-400">
                    {{ __('৳:amount is waiting to be paid to your number.', ['amount' => number_format($summary['pending']->amount, 2)]) }}
                </span>
            @elseif ($summary['minimum'] > 0)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('The smallest amount you can take out is ৳:amount.', ['amount' => number_format($summary['minimum'], 2)]) }}
                </span>
            @endif
        </div>
    </x-filament::section>

    {{ $this->table }}

    @if ($rewards->isNotEmpty())
        <x-filament::section :heading="__('Latest commissions')">
            <x-referral-rewards-table :rewards="$rewards" show-buyer />
        </x-filament::section>
    @endif

    @if ($withdrawals->isNotEmpty())
        <x-filament::section :heading="__('My withdrawals')">
            <x-data-table :columns="[__('Date') => 'start', __('Amount') => 'start', __('Status') => 'start', __('Details') => 'start']">
                        @foreach ($withdrawals as $withdrawal)
                            <tr>
                                <td>{{ $withdrawal->created_at->format('M j, Y') }}</td>
                                <td class="font-medium">৳{{ number_format($withdrawal->amount, 2) }}</td>
                                <td>
                                    <x-filament::badge :color="$withdrawal->status->getColor()">{{ $withdrawal->status->getLabel() }}</x-filament::badge>
                                </td>
                                <td class="text-gray-500 dark:text-gray-400">{{ $withdrawal->transaction_id ?? $withdrawal->note ?? '—' }}</td>
                            </tr>
                        @endforeach
            </x-data-table>
        </x-filament::section>
    @endif
    @if ($walletEntries->isNotEmpty())
        <x-filament::section :heading="__('My wallet')" icon="heroicon-o-wallet">
            <x-data-table :columns="[__('Date') => 'start', __('Details') => 'start', __('Amount') => 'start']">
                @foreach ($walletEntries as $entry)
                    <tr>
                        <td>{{ $entry['at']->format('M j, Y') }}</td>
                        <td>{{ $entry['label'] }}</td>
                        <td @class(['font-medium', 'text-success-600 dark:text-success-400' => $entry['amount'] > 0, 'text-danger-600 dark:text-danger-400' => $entry['amount'] < 0])>
                            {{ $entry['amount'] > 0 ? '+' : '−' }}৳{{ number_format(abs($entry['amount']), 2) }}
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
