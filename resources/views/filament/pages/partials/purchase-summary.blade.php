{{-- What a purchase costs and where to send the money (MySubscriptionPage's buy form). --}}
<div class="space-y-3 text-sm">
    <dl class="space-y-1">
        <div class="flex justify-between gap-4">
            <dt>{{ __('Plan price') }}</dt>
            <dd>৳{{ number_format($quote['list_price'], 2) }}</dd>
        </div>

        @if ($quote['discount'] > 0)
            <div class="flex justify-between gap-4 text-success-600 dark:text-success-400">
                <dt>{{ __('Referral discount') }}</dt>
                <dd>−৳{{ number_format($quote['discount'], 2) }}</dd>
            </div>
        @endif

        @if ($quote['wallet'] > 0)
            <div class="flex justify-between gap-4 text-success-600 dark:text-success-400">
                <dt>{{ __('Wallet credit') }}</dt>
                <dd>−৳{{ number_format($quote['wallet'], 2) }}</dd>
            </div>
        @endif

        <div class="flex justify-between gap-4 border-t border-gray-200 pt-1 text-base font-bold dark:border-white/10">
            <dt>{{ __('You pay') }}</dt>
            <dd>৳{{ number_format($quote['payable'], 2) }}</dd>
        </div>
    </dl>

    @if ($quote['payable'] <= 0)
        <p>{{ __('Your wallet credit covers the whole price — nothing to send.') }}</p>
    @elseif (empty($receivingNumbers))
        <p class="font-medium text-danger-600 dark:text-danger-400">
            {{ __('Payment is not available right now. Please try again later.') }}
        </p>
    @else
        <div>
            <p class="font-medium">
                {{ __('Send ৳:amount to one of these numbers, then fill in the details below:', ['amount' => number_format($quote['payable'], 2)]) }}
            </p>
            <ul class="mt-1 space-y-0.5">
                @foreach ($receivingNumbers as $provider => $number)
                    <li>
                        {{ \App\Enums\MobileBankingProvider::from($provider)->getLabel() }}:
                        <span class="font-bold">{{ $number }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        @if (filled($instructions))
            <p class="whitespace-pre-line text-gray-600 dark:text-gray-400">{{ $instructions }}</p>
        @endif
    @endif
</div>
