<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Exam'))</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/katex-embed-renderer.js'])
</head>
<body class="font-bangla bg-gray-50 min-h-screen px-4 py-6 sm:py-10">
    {{-- Back to the home page, and the exam pages' own language (SetLocale's
         "exam" scope, Bangla until changed). A page can leave either out: the
         exam paper itself has no way home, so nobody walks out of it by
         mistake; the result page has no language switch, since it is the
         answer to a form submission and switching would try to reload it. --}}
    @if (! $__env->hasSection('hide-home-link') || ! $__env->hasSection('hide-language-switcher'))
        <div class="max-w-2xl mx-auto mb-4 flex items-center justify-between gap-3">
            @sectionMissing('hide-home-link')
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    {{ __('Home page') }}
                </a>
            @else
                <span></span>
            @endif

            @sectionMissing('hide-language-switcher')
                <x-language-switcher for="exam" />
            @endif
        </div>
    @endif

    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
        @yield('content')
    </div>
</body>
</html>
