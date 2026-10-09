<x-filament-panels::page>
    @php
        ['subscription' => $subscription, 'plan' => $plan, 'monthly_limit' => $monthlyLimit] = $this->planSummary();
        $pendingPayment = $this->pendingPayment();
        $payments = $this->payments();
        $walletBalance = $this->walletBalance();
        $priceList = $this->priceList();
    @endphp

    <x-filament::section :heading="__('Current plan')">
        @if ($plan)
            <div class="space-y-2">
                <p class="text-lg font-bold">{{ $plan->name }}</p>
                <p class="text-sm text-gray-500">
                    {{ __('Monthly exam limit') }}:
                    <span class="font-medium">{{ $monthlyLimit ?? __('Unlimited') }}</span>
                </p>
                @if ($subscription?->ends_at)
                    <p class="text-sm text-gray-500">{{ __('Renews/expires') }}: {{ $subscription->ends_at->format('M j, Y') }}</p>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __("You don't have an active subscription yet.") }}</p>
        @endif

        <p class="mt-3 text-sm text-gray-500">
            {{ __('Wallet credit') }}: <span class="font-medium">৳{{ number_format($walletBalance, 2) }}</span>
        </p>
    </x-filament::section>

    @if ($pendingPayment)
        <x-filament::section :heading="__('Payment waiting for approval')" icon="heroicon-o-clock" icon-color="warning">
            <p class="text-sm">
                {{ __(':plan — ৳:amount (Transaction ID: :transaction)', [
                    'plan' => $pendingPayment->plan?->name,
                    'amount' => number_format($pendingPayment->amount, 2),
                    'transaction' => $pendingPayment->gateway_transaction_id,
                ]) }}
            </p>
            <p class="mt-1 text-sm text-gray-500">{{ __('Your subscription starts as soon as the payment is approved.') }}</p>
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('Available plans')">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->plans() as $availablePlan)
                @php $quote = $priceList[$availablePlan->id]; @endphp

                <div class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div>
                        <p class="font-bold">{{ $availablePlan->name }}</p>
                        <p class="text-sm text-gray-500">{{ $availablePlan->billing_cycle->getLabel() }}</p>
                    </div>

                    <p class="text-2xl font-bold">
                        ৳{{ number_format($quote['list_price'] - $quote['discount'], 2) }}
                        @if ($quote['discount'] > 0)
                            <span class="text-sm font-normal text-gray-500 line-through">৳{{ number_format($quote['list_price'], 2) }}</span>
                        @endif
                    </p>

                    <p class="text-sm text-gray-500">
                        {{ __('Monthly exam limit') }}:
                        <span class="font-medium">{{ $availablePlan->monthly_exam_limit ?? __('Unlimited') }}</span>
                    </p>

                    <x-filament::button
                        class="mt-auto"
                        wire:click="mountAction('buy', { plan: {{ $availablePlan->id }} })"
                        :disabled="$pendingPayment !== null"
                    >
                        {{ __('Buy') }}
                    </x-filament::button>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No plans are on sale right now.') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    @if ($payments->isNotEmpty())
        <x-filament::section :heading="__('My payments')">
            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full text-start text-sm">
                    <thead>
                        <tr>
                            <th class="p-2 text-start">{{ __('Date') }}</th>
                            <th class="p-2 text-start">{{ __('Plan') }}</th>
                            <th class="p-2 text-start">{{ __('Paid') }}</th>
                            <th class="p-2 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="p-2">{{ $payment->created_at->format('M j, Y') }}</td>
                                <td class="p-2">{{ $payment->plan?->name ?? '—' }}</td>
                                <td class="p-2">৳{{ number_format($payment->amount, 2) }}</td>
                                <td class="p-2">
                                    {{ $payment->status->getLabel() }}
                                    @if (filled($payment->review_note))
                                        <span class="block text-xs text-gray-500">{{ $payment->review_note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
