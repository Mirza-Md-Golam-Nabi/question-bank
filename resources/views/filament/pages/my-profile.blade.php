<x-filament-panels::page>
    @php
        $user = auth()->user();
        $settings = $this->settings();
        $referrer = $this->referrer();
        $studentReferral = $this->studentReferral();
    @endphp

    <x-filament::section :heading="__('My details')">
        <p class="text-sm"><span class="text-gray-500">{{ __('Name') }}:</span> {{ $user->name }}</p>
        <p class="mb-4 text-sm"><span class="text-gray-500">{{ __('Email') }}:</span> {{ $user->email }}</p>

        @if ($studentReferral)
            <div class="mb-4 rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">
                <p><span class="text-gray-500">{{ __('Referred by') }}:</span> <span class="font-medium">{{ $studentReferral->referrer->name }}</span> ({{ $studentReferral->source->getLabel() }})</p>
                <p class="mt-1 text-gray-500">
                    {{ __(':days days left — on :date it ends by itself.', ['days' => $studentReferral->daysLeft(), 'date' => $studentReferral->ends_at->format('M j, Y')]) }}
                    @if ($studentReferral->isChangeable())
                        {{ __('You can change the code until :date.', ['date' => $studentReferral->changeable_until->format('M j, Y g:i A')]) }}
                    @endif
                </p>
            </div>
        @elseif ($referrer)
            <p class="mb-4 text-sm"><span class="text-gray-500">{{ __('Referred by') }}:</span> {{ $referrer->name }}</p>
        @endif

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('Save') }}
            </x-filament::button>
        </form>
    </x-filament::section>

    @php $devices = $this->devices(); @endphp
    @if ($devices->isNotEmpty())
        <x-filament::section :heading="__('My devices')" icon="heroicon-o-device-phone-mobile">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('You can be signed in on :count devices at once. Signing in on another signs out the one used longest ago.', ['count' => $this->deviceLimit()]) }}
            </p>

            <ul class="mt-3 divide-y divide-gray-100 text-sm dark:divide-white/10">
                @foreach ($devices as $device)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                        <span>
                            <span class="font-medium">{{ $device['agent'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">· {{ $device['ip'] }} · {{ $device['last_active']->diffForHumans() }}</span>
                        </span>

                        @if ($device['is_current'])
                            <x-filament::badge color="success">{{ __('This device') }}</x-filament::badge>
                        @else
                            <x-filament::button size="xs" color="danger" outlined wire:click="signOutDevice(@js($device['id']))">
                                {{ __('Sign out') }}
                            </x-filament::button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($this->canRefer())
        <x-filament::section :heading="__('Refer and earn')" icon="heroicon-o-gift">
            <p class="text-sm">
                {{ __('Share your code. A student who enters it is under your referral for :days days and gets :discount% off; you earn :reward% of what they pay on :which they make in that time.', [
                    'days' => $settings->referral_lock_days,
                    'discount' => $settings->referee_discount_percent,
                    'reward' => $this->rewardPercent(),
                    'which' => $settings->commission_first_purchase_only ? __('the first purchase') : __('every purchase'),
                ]) }}
            </p>
            <p class="mt-1 text-sm text-gray-500">
                @switch($user->role)
                    @case(\App\Enums\UserRole::Teacher)
                        {{ __('Students who open your exam links while logged in also come under your referral. Your commission goes to your wallet and can be taken out as cash on the Referrals & commission page — only while you have a paid subscription.') }}
                        @break
                    @case(\App\Enums\UserRole::Student)
                        {{ __('Your commission goes to your wallet and can be taken out as cash on the Referrals & commission page — only while you have a paid subscription.') }}
                        @break
                    @default
                        {{ __('Your reward is added to your earnings and paid with your payout.') }}
                @endswitch
            </p>

            @if ($settings->refund_window_days > 0)
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('A reward becomes yours :days day(s) after the payment is approved, once it can no longer be refunded.', ['days' => $settings->refund_window_days]) }}
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

            {{-- What referring has earned is on one page per role, not here. --}}
            <x-filament::button tag="a" :href="$this->earningsUrl()" color="gray" size="sm" icon="heroicon-o-banknotes" class="mt-3">
                {{ __('See what I have earned') }}
            </x-filament::button>
        </x-filament::section>
    @endif

</x-filament-panels::page>
