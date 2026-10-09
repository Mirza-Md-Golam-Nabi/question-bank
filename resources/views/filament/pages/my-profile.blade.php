<x-filament-panels::page>
    @php
        $user = auth()->user();
        $settings = $this->settings();
        $referrer = $this->referrer();
        $hasWallet = $this->hasWallet();
        $rewards = $this->rewards();
    @endphp

    <x-filament::section :heading="__('My details')">
        <p class="text-sm"><span class="text-gray-500">{{ __('Name') }}:</span> {{ $user->name }}</p>
        <p class="mb-4 text-sm"><span class="text-gray-500">{{ __('Email') }}:</span> {{ $user->email }}</p>

        @if ($referrer)
            <p class="mb-4 text-sm"><span class="text-gray-500">{{ __('Referred by') }}:</span> {{ $referrer->name }}</p>
        @endif

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('Save') }}
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($this->canRefer())
        <x-filament::section :heading="__('Refer and earn')" icon="heroicon-o-gift">
            <p class="text-sm">
                @if ($hasWallet)
                    {{ __('Share your code. When someone who signed up with it buys their first subscription, :reward% of what they pay is added to your wallet — and they get :discount% off.', [
                        'reward' => $this->rewardPercent(),
                        'discount' => $settings->referee_discount_percent,
                    ]) }}
                @else
                    {{ __('Share your code. When someone who signed up with it buys their first subscription, :reward% of what they pay is added to your earnings and paid with your payout — and they get :discount% off.', [
                        'reward' => $this->rewardPercent(),
                        'discount' => $settings->referee_discount_percent,
                    ]) }}
                @endif
            </p>

            @if ($settings->refund_window_days > 0)
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('A reward becomes yours :days day(s) after the payment is approved, once it can no longer be refunded.', ['days' => $settings->refund_window_days]) }}
                </p>
            @endif

            @if ($hasWallet)
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('Wallet credit is not paid out as cash — you spend it on your own subscription.') }}
                </p>
            @endif

            <div
                class="mt-4 space-y-3"
                {{-- The values live here, not on the buttons: Blade does not
                     compile @js inside a component tag such as x-filament::button. --}}
                x-data="{
                    values: @js(['code' => $this->referralCode(), 'link' => $this->referralLink()]),
                    copied: null,
                    async copy(what) {
                        try {
                            await navigator.clipboard.writeText(this.values[what])
                        } catch (error) {
                            // The Clipboard API only exists on https and localhost.
                            const field = document.createElement('textarea')
                            field.value = this.values[what]
                            document.body.appendChild(field)
                            field.select()
                            document.execCommand('copy')
                            field.remove()
                        }

                        this.copied = what
                        setTimeout(() => (this.copied = null), 2000)
                    },
                }"
            >
                @foreach (['code' => __('Your code'), 'link' => __('Your link')] as $what => $label)
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm text-gray-500">{{ $label }}:</span>
                        @if ($what === 'code')
                            <span class="rounded-lg bg-gray-100 px-3 py-1 font-mono text-lg font-bold tracking-widest dark:bg-white/10">{{ $this->referralCode() }}</span>
                        @else
                            <span class="break-all text-sm">{{ $this->referralLink() }}</span>
                        @endif
                        <x-filament::button size="xs" color="gray" x-on:click="copy('{{ $what }}')">
                            <span x-show="copied !== '{{ $what }}'">{{ __('Copy') }}</span>
                            <span x-show="copied === '{{ $what }}'" x-cloak>{{ __('Copied') }}</span>
                        </x-filament::button>
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-sm">
                {{ __('People who joined with your code') }}: <span class="font-bold">{{ $this->referredCount() }}</span>
            </p>
        </x-filament::section>
    @endif

    @if ($rewards->isNotEmpty())
        <x-filament::section :heading="__('My referral rewards')" icon="heroicon-o-banknotes">
            <x-referral-reward-summary :summary="$this->rewardSummary()" :show-paid="! $hasWallet" />

            <div class="mt-4 overflow-x-auto">
                <table class="fi-ta-table w-full text-start text-sm">
                    <thead>
                        <tr>
                            <th class="p-2 text-start">{{ __('Date') }}</th>
                            <th class="p-2 text-start">{{ __('Amount') }}</th>
                            <th class="p-2 text-start">{{ __('Status') }}</th>
                            <th class="p-2 text-start">{{ __('Available from') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rewards as $reward)
                            <tr>
                                <td class="p-2">{{ $reward->created_at->format('M j, Y') }}</td>
                                <td class="p-2 font-medium">৳{{ number_format($reward->amount, 2) }}</td>
                                <td class="p-2">{{ $reward->status()->getLabel() }}</td>
                                <td class="p-2">{{ $reward->matures_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($hasWallet)
        @php $entries = $this->walletEntries(); @endphp

        <x-filament::section :heading="__('My wallet')" icon="heroicon-o-wallet">
            <p class="text-2xl font-bold">৳{{ number_format($this->walletBalance(), 2) }}</p>

            @if ($settings->credit_expiry_months)
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('Credit expires :months month(s) after it is added.', ['months' => $settings->credit_expiry_months]) }}
                </p>
            @endif

            @if ($entries->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="fi-ta-table w-full text-start text-sm">
                        <thead>
                            <tr>
                                <th class="p-2 text-start">{{ __('Date') }}</th>
                                <th class="p-2 text-start">{{ __('Details') }}</th>
                                <th class="p-2 text-start">{{ __('Amount') }}</th>
                                <th class="p-2 text-start">{{ __('Expires') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="p-2">{{ $entry['at']->format('M j, Y') }}</td>
                                    <td class="p-2">{{ $entry['label'] }}</td>
                                    <td @class(['p-2 font-medium', 'text-success-600 dark:text-success-400' => $entry['amount'] > 0, 'text-danger-600 dark:text-danger-400' => $entry['amount'] < 0])>
                                        {{ $entry['amount'] > 0 ? '+' : '−' }}৳{{ number_format(abs($entry['amount']), 2) }}
                                    </td>
                                    <td class="p-2">{{ $entry['expires_at']?->format('M j, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
