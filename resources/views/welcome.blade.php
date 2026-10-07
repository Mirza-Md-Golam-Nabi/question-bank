@php
    $panels = [
        [
            'title' => __('Student'),
            'description' => __('Take exams from a shared link, build your own practice exams and see your results.'),
            'route' => 'filament.student.auth.login',
            'method' => __('Sign in with Google'),
            'icon' => 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
            'iconClasses' => 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30',
            'glowClasses' => 'from-emerald-500/30',
            'buttonClasses' => 'bg-emerald-500 hover:bg-emerald-400 focus-visible:outline-emerald-400',
        ],
        [
            'title' => __('Teacher'),
            'description' => __('Add questions, build exams from the approved question bank and share them with your students.'),
            'route' => 'filament.teacher.auth.login',
            'method' => __('Sign in with Google'),
            'icon' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
            'iconClasses' => 'bg-sky-400/10 text-sky-300 ring-sky-400/30',
            'glowClasses' => 'from-sky-500/30',
            'buttonClasses' => 'bg-sky-500 hover:bg-sky-400 focus-visible:outline-sky-400',
        ],
        [
            'title' => __('Staff'),
            'description' => __('Add questions to the question bank and get paid for every approved question.'),
            'route' => 'filament.staff.auth.login',
            'method' => __('Sign in with Google'),
            'icon' => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
            'iconClasses' => 'bg-amber-400/10 text-amber-300 ring-amber-400/30',
            'glowClasses' => 'from-amber-500/30',
            'buttonClasses' => 'bg-amber-500 hover:bg-amber-400 focus-visible:outline-amber-400',
        ],
    ];

    $features = [
        [
            'title' => __('Verified question bank'),
            'description' => __('Every MCQ and creative question joins the question bank only after admin approval.'),
            'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ],
        [
            'title' => __('Exams from one link'),
            'description' => __('Teachers build an exam and share the link — students join by signing in or as a guest.'),
            'icon' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
        ],
        [
            'title' => __('Practise on your own'),
            'description' => __('Take a practice exam any time — auto-generated or hand-picked by you.'),
            'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ config('app.name') }} — {{ __('A platform for question banks, online exams and practice.') }}">
    <title>{{ config('app.name') }} — {{ __('Question bank and online exams') }}</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="font-bangla relative min-h-screen overflow-x-hidden bg-slate-950 text-slate-200 antialiased">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 left-1/2 h-[36rem] w-[36rem] -translate-x-1/2 rounded-full bg-indigo-600/30 blur-[120px]"></div>
        <div class="absolute top-72 -left-32 h-96 w-96 rounded-full bg-fuchsia-600/20 blur-[120px]"></div>
        <div class="absolute top-96 -right-32 h-96 w-96 rounded-full bg-sky-500/20 blur-[120px]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgb(255_255_255/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.04)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)]"></div>
    </div>

    <header class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 sm:gap-3">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-indigo-500 to-fuchsia-500 shadow-lg shadow-indigo-500/30 sm:size-10 sm:rounded-xl">
                <svg class="size-5 text-white sm:size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                </svg>
            </span>
            <span class="truncate text-sm font-semibold tracking-tight text-white sm:text-lg">{{ config('app.name') }}</span>
        </a>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <x-language-switcher dark />

            <a href="#panels" class="shrink-0 rounded-full bg-white/10 px-4 py-1.5 text-xs font-medium text-white ring-1 ring-white/15 transition hover:bg-white/20 sm:px-5 sm:py-2 sm:text-sm">
                {{ __('Sign in') }}
            </a>
        </div>
    </header>

    <main>
        <section class="mx-auto max-w-4xl px-4 pt-10 pb-14 text-center sm:px-6 sm:pt-20 sm:pb-20 lg:px-8 lg:pt-24">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1.5 text-xs text-indigo-200 ring-1 ring-white/10 sm:px-4 sm:text-sm">
                <span class="size-2 shrink-0 rounded-full bg-emerald-400 shadow-[0_0_12px] shadow-emerald-400"></span>
                {{ __('Question bank · Exams · Practice — on one platform') }}
            </span>

            <h1 class="mt-6 text-3xl leading-tight font-bold tracking-tight text-white sm:mt-8 sm:text-5xl sm:leading-tight lg:text-6xl lg:leading-tight">
                {{ __('Quality questions,') }}
                <span class="bg-linear-to-r from-indigo-300 via-fuchsia-300 to-sky-300 bg-clip-text text-transparent">{{ __('smart exams') }}</span>
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-xs leading-6 text-slate-300 sm:mt-6 sm:text-base sm:leading-8 lg:text-lg">
                {{ __('Build an exam in minutes from a bank of verified MCQ and creative questions, share it and see results instantly.') }}
            </p>

            <div class="mt-7 flex flex-col items-center justify-center gap-3 sm:mt-10 sm:flex-row sm:gap-4">
                <a href="#panels" class="w-full rounded-full bg-linear-to-r from-indigo-500 to-fuchsia-500 px-6 py-2.5 text-xs font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:brightness-110 sm:w-auto sm:px-8 sm:py-3 sm:text-base">
                    {{ __('Go to your panel') }}
                </a>
                <a href="#features" class="w-full rounded-full px-6 py-2.5 text-xs font-semibold text-slate-200 ring-1 ring-white/15 transition hover:bg-white/5 sm:w-auto sm:px-8 sm:py-3 sm:text-base">
                    {{ __('Learn more') }}
                </a>
            </div>
        </section>

        <section id="panels" class="mx-auto max-w-6xl scroll-mt-6 px-4 pb-14 sm:scroll-mt-10 sm:px-6 sm:pb-24 lg:px-8">
            <div class="text-center">
                <h2 class="text-xl font-bold tracking-tight text-white sm:text-3xl lg:text-4xl">{{ __('Which panel do you want to sign in to?') }}</h2>
                <p class="mt-2 text-xs text-slate-400 sm:mt-3 sm:text-base">{{ __('Choose the panel that matches your role.') }}</p>
            </div>

            <div class="mt-8 grid gap-4 sm:mt-12 sm:gap-6 md:grid-cols-3">
                @foreach ($panels as $panel)
                    <article class="group relative flex flex-col overflow-hidden rounded-2xl bg-white/5 p-5 ring-1 sm:rounded-3xl sm:p-7 ring-white/10 backdrop-blur transition duration-300 hover:-translate-y-1.5 hover:bg-white/[0.07] hover:ring-white/25">
                        <div aria-hidden="true" class="{{ $panel['glowClasses'] }} absolute -top-24 -right-24 size-56 rounded-full bg-radial to-transparent opacity-0 blur-2xl transition duration-500 group-hover:opacity-100"></div>

                        <span class="{{ $panel['iconClasses'] }} relative flex size-11 items-center justify-center rounded-xl ring-1 sm:size-14 sm:rounded-2xl">
                            <svg class="size-6 sm:size-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $panel['icon'] }}" />
                            </svg>
                        </span>

                        <h3 class="relative mt-4 text-lg font-bold text-white sm:mt-6 sm:text-2xl">{{ $panel['title'] }}</h3>
                        <p class="relative mt-2 flex-1 text-xs leading-5 text-slate-400 sm:mt-3 sm:text-base sm:leading-7">{{ $panel['description'] }}</p>

                        <a href="{{ route($panel['route']) }}" class="{{ $panel['buttonClasses'] }} relative mt-5 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-950 transition focus-visible:outline-2 focus-visible:outline-offset-2 sm:mt-8 sm:rounded-xl sm:px-5 sm:py-3 sm:text-base">
                            {{ __(':role login', ['role' => $panel['title']]) }}
                            <svg class="size-4 transition sm:size-5 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                        <p class="relative mt-3 text-center text-xs text-slate-500">{{ $panel['method'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="features" class="mx-auto max-w-6xl scroll-mt-6 px-4 pb-14 sm:scroll-mt-10 sm:px-6 sm:pb-24 lg:px-8">
            <div class="grid gap-6 rounded-2xl bg-white/[0.03] p-5 ring-1 ring-white/10 sm:gap-8 sm:rounded-3xl sm:p-10 md:grid-cols-3 lg:p-12">
                @foreach ($features as $feature)
                    <div>
                        <span class="flex size-10 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-300 ring-1 ring-indigo-400/30 sm:size-12 sm:rounded-xl">
                            <svg class="size-5 sm:size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}" />
                            </svg>
                        </span>
                        <h3 class="mt-3 text-sm font-semibold text-white sm:mt-5 sm:text-lg">{{ $feature['title'] }}</h3>
                        <p class="mt-1.5 text-xs leading-5 text-slate-400 sm:mt-2 sm:text-base sm:leading-7">{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="border-t border-white/10">
        <div class="mx-auto max-w-7xl px-4 py-5 text-center text-xs text-slate-500 sm:px-6 sm:py-8 sm:text-sm lg:px-8">
            &copy; {{ now()->year }} {{ __(':name. All rights reserved.', ['name' => config('app.name')]) }}
        </div>
    </footer>
</body>
</html>
