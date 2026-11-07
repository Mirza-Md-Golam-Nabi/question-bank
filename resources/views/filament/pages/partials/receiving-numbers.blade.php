{{-- Where to send a manual payment of ৳$amount — a purchase or the difference of a plan change. --}}
@if (empty($receivingNumbers))
    <p class="font-medium text-danger-600 dark:text-danger-400">
        {{ __('Payment is not available right now. Please try again later.') }}
    </p>
@else
    <div>
        <p class="font-medium">
            {{ __('Send ৳:amount to one of these numbers, then fill in the details below:', ['amount' => number_format($amount, 2)]) }}
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
