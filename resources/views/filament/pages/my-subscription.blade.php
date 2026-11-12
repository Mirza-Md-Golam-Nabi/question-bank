<x-filament-panels::page>
    @php
        $planDescriptions = $this->planDescriptions();
        $pendingPayment = $this->pendingPayment();
        $payments = $this->payments();
        $walletBalance = $this->walletBalance();
        $priceList = $this->priceList();
    @endphp

    <x-filament::section :heading="__('My plans')">
        <x-plan-usage :summary="$this->planSummary()" />

        <p class="mt-3 text-sm text-gray-500">
            {{ __('Wallet credit') }}: <span class="font-medium">৳{{ number_format($walletBalance, 2) }}</span>
        </p>
    </x-filament::section>

    @php $changeableSubscriptions = $this->changeableSubscriptions(); @endphp

    @if ($changeableSubscriptions->isNotEmpty())
        <x-filament::section :heading="__('Change my subscription')" icon="heroicon-o-arrows-right-left">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Move to another plan: a dearer one costs the difference, a cheaper one gives the difference back less a :percent% fee. The new length counts from when the subscription started.', ['percent' => \App\Models\BillingSetting::current()->downgradeFeeLabel()]) }}
            </p>

            <ul class="mt-3 divide-y divide-gray-100 text-sm dark:divide-white/10">
                @foreach ($changeableSubscriptions as $changeable)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                        <span>
                            <span class="font-medium">{{ $changeable['subscription']->plan?->name }}</span>
                            <span class="text-gray-500 dark:text-gray-400">
                                — {{ $changeable['subscription']->ends_at ? __('until :date', ['date' => \App\Enums\Locale::current()->writeDigits($changeable['subscription']->ends_at->translatedFormat('j F Y'))]) : __('No end') }}
                            </span>
                        </span>
                        <span class="flex flex-wrap gap-2">
                            @if ($changeable['plan'] && $pendingPayment === null)
                                {{ ($this->changePlanAction)(['subscription' => $changeable['subscription']->id]) }}
                            @endif
                            @if ($changeable['selections'])
                                {{ ($this->changeSelectionsAction)(['subscription' => $changeable['subscription']->id]) }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @foreach ($this->groupSubscriptions() as $groupSubscription)
        <x-filament::section :heading="__('People on my plan: :plan', ['plan' => $groupSubscription->plan?->name])" icon="heroicon-o-users">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __(':used of :seats seats used, yours included.', ['used' => $groupSubscription->currentMembers->count() + 1, 'seats' => $groupSubscription->seats()]) }}
            </p>

            <ul class="mt-3 divide-y divide-gray-100 text-sm dark:divide-white/10">
                @foreach ($groupSubscription->currentMembers as $member)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span>
                            {{ $member->email }}
                            <span class="text-gray-500 dark:text-gray-400">— {{ $member->user?->name ?? __('has not signed in yet') }}</span>
                        </span>
                        {{ ($this->removeMemberAction)(['member' => $member->id]) }}
                    </li>
                @endforeach
            </ul>

            @if ($groupSubscription->currentMembers->count() + 1 < $groupSubscription->seats())
                <div class="mt-3">
                    {{ ($this->addMemberAction)(['subscription' => $groupSubscription->id]) }}
                </div>
            @endif
        </x-filament::section>
    @endforeach

    @if ($pendingPayment)
        <x-filament::section :heading="__('Payment waiting for approval')" icon="heroicon-o-clock" icon-color="warning">
            @if ($pendingPayment->isPlanChange())
                <p class="text-sm">
                    {{ (float) $pendingPayment->refund_amount > 0
                        ? __('Change :from to :plan — ৳:amount to be given back to you', ['from' => $pendingPayment->previousPlan?->name, 'plan' => $pendingPayment->plan?->name, 'amount' => number_format((float) $pendingPayment->refund_amount, 2)])
                        : __('Change :from to :plan — ৳:amount (Transaction ID: :transaction)', ['from' => $pendingPayment->previousPlan?->name, 'plan' => $pendingPayment->plan?->name, 'amount' => number_format($pendingPayment->amount, 2), 'transaction' => $pendingPayment->gateway_transaction_id]) }}
                </p>
                <p class="mt-1 text-sm text-gray-500">{{ __('Your plan changes as soon as the Admin approves it.') }}</p>
            @else
            <p class="text-sm">
                {{ __(':plan — ৳:amount (Transaction ID: :transaction)', [
                    'plan' => $pendingPayment->itemLabel(),
                    'amount' => number_format($pendingPayment->amount, 2),
                    'transaction' => $pendingPayment->gateway_transaction_id,
                ]) }}
            </p>
            <p class="mt-1 text-sm text-gray-500">{{ $pendingPayment->isForInstitutionName() ? __('The name can be printed as soon as the payment is approved.') : __('Your subscription starts as soon as the payment is approved.') }}</p>
            @endif
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('Available plans')">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->plans() as $availablePlan)
                @php $quote = $priceList[$availablePlan->id]; @endphp

                <div class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div>
                        <p class="font-bold">{{ $availablePlan->name }}</p>
                        <p class="text-sm text-gray-500">{{ $availablePlan->durationLabel() }}</p>
                    </div>

                    <p class="text-2xl font-bold">
                        @if ($availablePlan->is_trial)
                            {{ __('Free trial') }}
                        @else
                            ৳{{ number_format($quote['list_price'] - $quote['discount'], 2) }}
                            @if ($quote['discount'] > 0)
                                <span class="text-sm font-normal text-gray-500 line-through">৳{{ number_format($quote['list_price'], 2) }}</span>
                            @endif
                        @endif
                    </p>

                    <ul class="list-inside list-disc space-y-0.5 text-sm text-gray-600 dark:text-gray-300">
                        @foreach ($planDescriptions[$availablePlan->id] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>

                    <x-filament::button
                        class="mt-auto"
                        wire:click="mountAction('buy', { plan: {{ $availablePlan->id }} })"
                        :disabled="$pendingPayment !== null"
                    >
                        {{ $availablePlan->is_trial ? __('Start free trial') : __('Buy') }}
                    </x-filament::button>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No plans are on sale right now.') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    @if ($payments->isNotEmpty())
        <x-filament::section :heading="__('My payments')">
            <x-data-table :columns="[__('Date') => 'start', __('Plan') => 'start', __('Paid') => 'start', __('Status') => 'start']">
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ $payment->created_at->format('M j, Y') }}</td>
                                <td>
                                    {{ $payment->itemLabel() ?: '—' }}
                                    @if ($payment->isPlanChange())
                                        <span class="block text-xs text-gray-500">{{ __('Changed from :plan', ['plan' => $payment->previousPlan?->name ?? '—']) }}</span>
                                    @endif
                                </td>
                                <td>
                                    ৳{{ number_format($payment->amount, 2) }}
                                    @if ((float) $payment->refund_amount > 0)
                                        <span class="block text-xs text-gray-500">{{ __('Given back: ৳:amount', ['amount' => number_format((float) $payment->refund_amount, 2)]) }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $payment->status->getLabel() }}
                                    @if (filled($payment->review_note))
                                        <span class="block text-xs text-gray-500">{{ $payment->review_note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
            </x-data-table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
