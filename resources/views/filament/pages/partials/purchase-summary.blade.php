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
    @else
        @include('filament.pages.partials.receiving-numbers', ['amount' => $quote['payable']])
    @endif
</div>
