@php
    $rows = $this->rows();
    $totals = $this->totals();

    // Column heading => the figure it shows, for the table and the totals alike.
    $moneyColumns = [
        __('Cash received') => 'cash_received',
        __('Rewards maturing') => 'rewards_maturing',
        __('Rewards matured') => 'rewards_matured',
        __('Rewards cancelled') => 'rewards_cancelled',
    ];
    $countColumns = [
        __('Referred') => 'referred',
        __('Bought') => 'buyers',
    ];
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section :heading="__('Totals')">
        <dl class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($countColumns as $heading => $key)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $heading }}</dt>
                    <dd class="text-xl font-bold">{{ $totals[$key] }}</dd>
                </div>
            @endforeach
            @foreach ($moneyColumns as $heading => $key)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $heading }}</dt>
                    <dd class="text-xl font-bold">৳{{ number_format($totals[$key], 2) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section :heading="__('By referrer')">
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Nobody has referred anyone yet.') }}</p>
        @else
            <div class="qb-result-sheet-wrap">
                <table class="qb-result-sheet">
                    <thead>
                        <tr>
                            <th>{{ __('Referrer') }}</th>
                            <th>{{ __('Role') }}</th>
                            @foreach ($countColumns as $heading => $key)
                                <th class="qb-result-sheet-num">{{ $heading }}</th>
                            @endforeach
                            @foreach ($moneyColumns as $heading => $key)
                                <th class="qb-result-sheet-num">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $referrer)
                            <tr wire:key="referrer-{{ $referrer->id }}">
                                <td>{{ $referrer->name }}</td>
                                <td>{{ $referrer->role->getLabel() }}</td>
                                <td class="qb-result-sheet-num">{{ $referrer->referred_count }}</td>
                                <td class="qb-result-sheet-num">{{ $referrer->buyers_count }}</td>
                                @foreach ($moneyColumns as $key)
                                    <td class="qb-result-sheet-num">৳{{ number_format((float) $referrer->{$key}, 2) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <x-filament::pagination :paginator="$rows" />
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
