{{-- The sign-in choices, one card per panel — on the home page and on /app,
     where the installed app opens for someone not signed in. --}}
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
        [
            'title' => __('School'),
            'description' => __('One account for the school: its own questions, exams for its students and marks sent to the school software.'),
            'route' => 'filament.school.auth.login',
            'method' => __('Sign in with Google'),
            'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z',
            'iconClasses' => 'bg-violet-400/10 text-violet-300 ring-violet-400/30',
            'glowClasses' => 'from-violet-500/30',
            'buttonClasses' => 'bg-violet-500 hover:bg-violet-400 focus-visible:outline-violet-400',
        ],
    ];
@endphp

<div {{ $attributes->class('grid gap-4 sm:gap-6 md:grid-cols-2 xl:grid-cols-4') }}>
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
