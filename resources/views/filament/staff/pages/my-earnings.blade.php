<x-filament-panels::page>
    @php
        $summaryCards = [
            [
                'label' => __('Questions Approved'),
                'value' => $this->totalQuestionsApproved(),
                'icon' => 'heroicon-o-check-badge',
                'gradient' => 'from-emerald-400 to-teal-600',
                // Explicit `panel: 'staff'` rather than relying on
                // Filament::getCurrentPanel() — that ambient state isn't
                // set when this page is rendered outside a real HTTP
                // request (e.g. Livewire-component-only tests), which
                // resolved this to the wrong (default) panel's route.
                'url' => \App\Filament\Staff\Pages\ApprovedQuestionsBreakdown::getUrl(panel: 'staff'),
            ],
            [
                'label' => __('Questions Pending'),
                'value' => $this->pendingQuestionsCount(),
                'icon' => 'heroicon-o-clock',
                'gradient' => 'from-amber-400 to-orange-500',
                'url' => \App\Filament\Staff\Pages\PendingQuestionsBreakdown::getUrl(panel: 'staff'),
            ],
            [
                'label' => __('Total Earned'),
                'value' => '৳'.number_format($this->totalEarned(), 2),
                'icon' => 'heroicon-o-banknotes',
                'gradient' => 'from-sky-400 to-blue-600',
                'url' => \App\Filament\Staff\Pages\MonthlyEarnings::getUrl(panel: 'staff'),
            ],
            [
                'label' => __('Total Paid'),
                'value' => '৳'.number_format($this->totalPaid(), 2),
                'icon' => 'heroicon-o-wallet',
                'gradient' => 'from-fuchsia-400 to-purple-600',
                'url' => \App\Filament\Staff\Pages\PayoutHistory::getUrl(panel: 'staff'),
            ],
        ];
    @endphp

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($summaryCards as $card)
            <{{ isset($card['url']) ? 'a' : 'div' }}
                @if (isset($card['url']))
                    href="{{ $card['url'] }}"
                    wire:navigate
                    class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-gray-900"
                @else
                    class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
                @endif
            >
                <div class="absolute inset-0 bg-gradient-to-br {{ $card['gradient'] }} opacity-[0.08] dark:opacity-[0.15]"></div>

                <div class="relative flex items-center gap-3 p-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $card['gradient'] }} text-white shadow-lg lg:h-14 lg:w-14 lg:rounded-2xl">
                        <x-filament::icon :icon="$card['icon']" class="h-5 w-5 lg:h-7 lg:w-7" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <span class="block text-xs leading-tight text-gray-500 dark:text-gray-400 lg:text-sm">{{ $card['label'] }}</span>
                        <p class="text-xl font-bold text-gray-950 dark:text-white lg:text-2xl">{{ $card['value'] }}</p>
                    </div>

                    @if (isset($card['url']))
                        <x-filament::icon
                            icon="heroicon-o-chevron-right"
                            class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-hover:translate-x-0.5 dark:text-gray-500"
                        />
                    @endif
                </div>
            </{{ isset($card['url']) ? 'a' : 'div' }}>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-3 py-1.5 text-sm font-bold text-white shadow-sm lg:text-base">
                <x-filament::icon icon="heroicon-o-chart-bar" class="h-4 w-4 lg:h-5 lg:w-5" />
                {{ __('Subject-wise breakdown') }}
            </span>
        </x-slot>

        <table class="fi-ta-table w-full text-start [&_*]:text-xs! lg:[&_*]:text-sm!">
            <thead>
                <tr>
                    <th class="p-2 text-start">{{ __('Class') }}</th>
                    <th class="p-2 text-start">{{ __('Subject') }}</th>
                    <th class="p-2 text-center">{{ __('Approved questions') }}</th>
                    <th class="p-2 text-center">{{ __('Earned') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->subjectBreakdown() as $row)
                    <tr>
                        <td class="p-2">{{ $row['class'] }}</td>
                        <td class="p-2">{{ $row['subject'] }}</td>
                        <td class="p-2 text-center">{{ $row['count'] }}</td>
                        <td class="p-2 text-center">৳{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-2" colspan="4">{{ __('No earnings yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    @php $referralRewards = $this->referralRewardSummary(); @endphp

    @if (array_sum($referralRewards) > 0)
        <x-filament::section :heading="__('Referral earnings')" icon="heroicon-o-gift">
            <x-referral-reward-summary :summary="$referralRewards" show-paid />

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Paid together with your question earnings once the refund period has passed.') }}
                <x-filament::link :href="\App\Filament\Staff\Pages\MyProfile::getUrl(panel: 'staff')" wire:navigate>
                    {{ __('See the details') }}
                </x-filament::link>
            </p>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-rose-500 to-orange-500 px-3 py-1.5 text-sm font-bold text-white shadow-sm lg:text-base">
                <x-filament::icon icon="heroicon-o-credit-card" class="h-4 w-4 lg:h-5 lg:w-5" />
                {{ __('Bank / mobile banking info') }}
            </span>
        </x-slot>

        <form
            wire:submit="saveBankInfo"
            class="space-y-4 [&_*]:text-xs! lg:[&_*]:text-sm!"
        >
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('Save') }}
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
