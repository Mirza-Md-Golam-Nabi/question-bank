{{-- Where the installed app opens for someone not signed in (PwaController):
     which panel to sign in to, and the offers on now. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('pwa.name') }}</title>
    @fonts
    @vite(['resources/css/app.css'])
    <x-pwa-head />
</head>
<body class="font-bangla min-h-screen bg-slate-950 text-slate-200 antialiased">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="flex items-center justify-between gap-3">
            <span class="text-lg font-semibold tracking-tight text-white sm:text-xl">{{ config('pwa.name') }}</span>
            <x-language-switcher variant="dark" />
        </div>

        <h1 class="mt-8 text-center text-xl font-bold tracking-tight text-white sm:mt-12 sm:text-3xl">{{ __('Which panel do you want to sign in to?') }}</h1>
        <p class="mt-2 text-center text-xs text-slate-400 sm:text-base">{{ __('Choose the panel that matches your role.') }}</p>

        <x-panel-choices class="mt-8" />

        @if ($offers->isNotEmpty())
            <h2 class="mt-12 text-center text-lg font-bold text-white sm:text-2xl">{{ __('Offers and news') }}</h2>
            <div class="mx-auto mt-6 max-w-4xl space-y-4">
                @foreach ($offers as $offer)
                    <x-offer-card :offer="$offer" show-audience />
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
