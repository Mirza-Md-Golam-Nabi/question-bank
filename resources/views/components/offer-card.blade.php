@props([
    /** @var \App\Models\Offer $offer */
    'offer',
    // Whether the signed-in user can close it for good (the panels' banner); not on the home page.
    'dismissible' => false,
    // Who it is for, as small tags — on the home page, where everyone reads it.
    'showAudience' => false,
])

{{-- One offer — the same card on the home page, on /app and in the panels'
     dashboard banner (filament.support.offer-banner). --}}
<article
    {{ $attributes->class('relative overflow-hidden rounded-2xl bg-linear-to-br from-indigo-600 to-fuchsia-600 p-4 text-white shadow-lg sm:p-5') }}
    @if ($dismissible)
        x-data="{ open: true }"
        x-show="open"
    @endif
>
    @if ($dismissible)
        <button
            type="button"
            x-on:click="open = false; fetch(@js(route('offers.dismiss', $offer)), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, Accept: 'application/json' } })"
            class="absolute top-2 right-2 rounded-full p-1.5 text-white/70 transition hover:bg-white/15 hover:text-white" aria-label="{{ __('Close') }}">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        @if ($offer->image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($offer->image) }}" alt="" class="h-28 w-full shrink-0 rounded-xl object-cover sm:h-24 sm:w-36">
        @endif

        <div class="min-w-0 flex-1 pr-6">
            @if ($showAudience)
                <div class="mb-2 flex flex-wrap gap-1.5">
                    @foreach ($offer->audienceLabels() as $label)
                        <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium">{{ $label }}</span>
                    @endforeach
                </div>
            @endif

            <h3 class="text-base font-bold sm:text-lg">{{ $offer->title }}</h3>
            <p class="mt-1 text-sm whitespace-pre-line text-white/85">{{ $offer->body }}</p>

            @if ($offer->ends_at)
                <p class="mt-2 text-xs text-white/70">{{ __('Until :date', ['date' => \App\Enums\Locale::current()->writeDigits($offer->ends_at->translatedFormat('j M Y, g:i A'))]) }}</p>
            @endif
        </div>

        @if ($offer->button_url)
            <a href="{{ $offer->button_url }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-white/90">
                {{ $offer->button_label ?: __('See the offer') }}
            </a>
        @endif
    </div>
</article>
