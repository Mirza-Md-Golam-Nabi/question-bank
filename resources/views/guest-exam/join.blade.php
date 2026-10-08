@extends('layouts.guest-exam')

@section('title', $exam->title)

@section('content')
    @php
        // Both forms on this page post the same two fields; which one failed
        // is told apart by the `result` error only the lookup produces.
        $startFormFailed = $errors->any() && ! $errors->has('result');
    @endphp

    <h1 class="text-2xl font-bold mb-2">{{ $exam->title }}</h1>
    <p class="text-gray-500 mb-6"><x-exam-summary :exam="$exam" /></p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('filament.student.auth.login') }}" class="qb-btn qb-btn--outline qb-btn--large">
            {{ __('Login to attempt') }}
        </a>

        <button type="button" onclick="document.getElementById('guest-form').classList.remove('hidden')" class="qb-btn qb-btn--primary qb-btn--large">
            {{ __('Continue as guest') }}
        </button>
    </div>

    <form id="guest-form" method="POST" action="{{ route('guest-exam.start', $exam->share_token) }}" @class(['mt-6 space-y-4', 'hidden' => ! $startFormFailed])>
        @csrf

        <x-guest-identity-fields id-prefix="start" :refill="$startFormFailed">
            {{ __('Write your name and phone/email correctly — you will need exactly these to see your answers later.') }}
        </x-guest-identity-fields>

        @if ($startFormFailed)
            @foreach ($errors->all() as $message)
                <p class="text-sm text-red-600">{{ $message }}</p>
            @endforeach
        @endif

        <button type="submit" class="qb-btn qb-btn--primary">{{ __('Start exam') }}</button>
    </form>

    @include('guest-exam.partials.result-lookup', ['exam' => $exam])
@endsection
