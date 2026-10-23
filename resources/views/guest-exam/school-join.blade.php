@extends('layouts.guest-exam')

@section('title', $exam->title)

@section('content')
    @php
        // Both forms on this page are told apart by the `result` error only
        // the lookup produces.
        $startFormFailed = $errors->any() && ! $errors->has('result');
    @endphp

    <h1 class="text-2xl font-bold mb-2">{{ $exam->title }}</h1>
    <p class="text-gray-500 mb-6"><x-exam-summary :exam="$exam" /></p>

    <form method="POST" action="{{ route('guest-exam.school-start', $exam->share_token) }}" class="space-y-4">
        @csrf

        <x-school-student-fields :classes="$classes" id-prefix="start" :refill="$startFormFailed" />

        <p class="text-xs text-gray-500">{{ __('Give your class, section and roll exactly as in school — your marks go to your school result by them.') }}</p>

        @if ($startFormFailed)
            @foreach ($errors->all() as $message)
                <p class="text-sm text-red-600">{{ $message }}</p>
            @endforeach
        @endif

        <button type="submit" class="qb-btn qb-btn--primary">{{ __('Start exam') }}</button>
    </form>

    @include('guest-exam.partials.school-result-lookup', ['exam' => $exam, 'classes' => $classes])
@endsection
