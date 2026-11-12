<x-filament-panels::page>
    @php
        $names = $this->names();
        $service = $this->service();
        $pendingPayment = $this->pendingPayment();
        $hasFreeName = $names->contains('is_free', true);
        // Dates with the digits in the viewer's language.
        $digits = fn (string $text): string => \App\Enums\Locale::current()->writeDigits($text);
    @endphp

    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('The name of your school or coaching centre, printed at the top of your question papers. The first name is free; every other name is paid for by the period, and when its days run out only the first one can be printed until you pay again.') }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            @unless ($hasFreeName)
                {{ $this->addFreeNameAction }}
            @elseif ($this->isOnSale() && ! $pendingPayment)
                {{ $this->addPaidNameAction }}
            @endunless
        </div>
    </x-filament::section>

    @if ($pendingPayment)
        <x-filament::section :heading="__('Payment waiting for approval')" icon="heroicon-o-clock" icon-color="warning">
            <p class="text-sm">
                {{ __(':plan — ৳:amount (Transaction ID: :transaction)', [
                    'plan' => $pendingPayment->itemLabel(),
                    'amount' => number_format($pendingPayment->amount, 2),
                    'transaction' => $pendingPayment->gateway_transaction_id ?? '—',
                ]) }}
            </p>
            <p class="mt-1 text-sm text-gray-500">{{ __('Another payment can be made once this one is approved.') }}</p>
        </x-filament::section>
    @endif

    @if ($names->isNotEmpty())
        <x-filament::section :heading="__('My names')">
            <x-data-table :columns="[__('Name') => 'start', __('Status') => 'start', '' => 'start']">
                @foreach ($names as $institutionName)
                    @php
                        $nextRenameAt = $service->nextRenameAt($institutionName);
                        $isWaiting = $institutionName->payments->contains(fn ($payment) => $payment->isPending());
                    @endphp
                    <tr>
                        <td class="font-medium">{{ $institutionName->name }}</td>
                        <td>
                            @if ($institutionName->is_free)
                                <x-filament::badge color="success">{{ __('Free') }}</x-filament::badge>
                            @elseif ($institutionName->isUsable())
                                <x-filament::badge color="success">{{ __('Paid until :date', ['date' => $digits($institutionName->paid_until->translatedFormat('j F Y'))]) }}</x-filament::badge>
                            @elseif ($isWaiting)
                                <x-filament::badge color="warning">{{ __('Waiting for approval') }}</x-filament::badge>
                            @elseif ($institutionName->paid_until)
                                <x-filament::badge color="danger">{{ __('Days ran out on :date', ['date' => $digits($institutionName->paid_until->translatedFormat('j F Y'))]) }}</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">{{ __('Not paid') }}</x-filament::badge>
                            @endif

                            @if ($nextRenameAt)
                                <span class="mt-1 block text-xs text-gray-500">{{ __('Can be changed again on :date', ['date' => $digits($nextRenameAt->translatedFormat('j F Y'))]) }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-2">
                                {{-- A button rendered with arguments doesn't check its own visibility. --}}
                                @unless ($nextRenameAt)
                                    {{ ($this->renameAction)(['name' => $institutionName->id]) }}
                                @endunless

                                @if (! $institutionName->is_free && $this->isOnSale() && ! $pendingPayment)
                                    {{ ($this->renewAction)(['name' => $institutionName->id]) }}
                                @endif

                                @if (! $isWaiting && $service->canDelete($institutionName))
                                    {{ ($this->deleteAction)(['name' => $institutionName->id]) }}
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
