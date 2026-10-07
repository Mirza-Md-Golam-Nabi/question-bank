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
    <div class="max-w-2xl mx-auto mb-4 flex justify-end">
        <x-language-switcher />
    </div>

    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
        @yield('content')
    </div>
</body>
</html>
