{{-- What moving a subscription to another plan comes to (SubscriptionChangeActions) — $quote is SubscriptionChangeService::quote(). --}}
<div class="space-y-3 text-sm">
    <dl class="space-y-1">
        <div class="flex justify-between gap-4">
            <dt>{{ __('Current plan counted as') }}</dt>
            <dd>৳{{ number_format($quote['credit'], 2) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt>{{ __('New plan price') }}</dt>
            <dd>৳{{ number_format($quote['price'], 2) }}</dd>
        </div>

        @if ($quote['refund'] > 0)
            <div class="flex justify-between gap-4">
                <dt>{{ __('Difference') }}</dt>
                <dd>৳{{ number_format($quote['credit'] - $quote['price'], 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4 text-gray-500 dark:text-gray-400">
                <dt>{{ __('Kept for the change (:percent%)', ['percent' => \App\Models\BillingSetting::current()->downgradeFeeLabel()]) }}</dt>
                <dd>−৳{{ number_format($quote['credit'] - $quote['price'] - $quote['refund'], 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-t border-gray-200 pt-1 text-base font-bold dark:border-white/10">
                <dt>{{ $showPaymentInfo ? __('You get back') : __('They get back') }}</dt>
                <dd>৳{{ number_format($quote['refund'], 2) }}</dd>
            </div>
        @else
            <div class="flex justify-between gap-4 border-t border-gray-200 pt-1 text-base font-bold dark:border-white/10">
                <dt>{{ __('To pay') }}</dt>
                <dd>৳{{ number_format($quote['pay'], 2) }}</dd>
            </div>
        @endif
    </dl>

    <p>
        {{ $quote['ends_at']
            ? __('It then runs until :date — counted from when the subscription started.', ['date' => \App\Enums\Locale::current()->writeDigits($quote['ends_at']->translatedFormat('j F Y'))])
            : __('It then has no end.') }}
    </p>

    @if ($showPaymentInfo && $quote['pay'] > 0)
        @include('filament.pages.partials.receiving-numbers', ['amount' => $quote['pay']])
    @elseif ($showPaymentInfo && $quote['refund'] > 0)
        <p class="font-medium">{{ __('Say where to send the money; the plan changes once the Admin has sent it.') }}</p>
    @endif
</div>
