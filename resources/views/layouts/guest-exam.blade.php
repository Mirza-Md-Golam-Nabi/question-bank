<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Exam')</title>
    @vite(['resources/css/app.css', 'resources/js/katex-embed-renderer.js'])
</head>
<body class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6">
        @yield('content')
    </div>
</body>
</html>
