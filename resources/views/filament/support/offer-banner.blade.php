{{-- The top of a dashboard (PanelDefaults): "Get started" for a new user
     (OnboardingChecklist), the button to switch push notifications on, and
     the offers for this user's role and class they haven't closed
     (Offer::scopeInBannerOf()). --}}
<div class="mb-6 space-y-3">
    @if ($onboarding)
        <x-guides.onboarding-checklist :steps="$onboarding" />
    @endif

    <x-push-prompt />

    @foreach ($offers as $offer)
        <x-offer-card :offer="$offer" dismissible />
    @endforeach
</div>
