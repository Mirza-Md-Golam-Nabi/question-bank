@extends('layouts.guest-exam')

@section('title', $exam->title)

@section('content')
    <h1 class="text-2xl font-bold mb-2">{{ $exam->title }}</h1>
    <p class="text-gray-500 mb-6">{{ __(':minutes minutes', ['minutes' => $exam->duration_minutes]) }} &middot; {{ __(':marks marks', ['marks' => $exam->total_marks]) }}</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('filament.student.auth.login') }}"
           class="block text-center rounded-lg border border-gray-300 px-4 py-3 font-medium hover:bg-gray-50">
            {{ __('Login to attempt') }}
        </a>

        <button type="button" onclick="document.getElementById('guest-form').classList.remove('hidden')"
                class="block text-center rounded-lg bg-amber-500 text-white px-4 py-3 font-medium hover:bg-amber-600">
            {{ __('Continue as guest') }}
        </button>
    </div>

    <form id="guest-form" method="POST" action="{{ route('guest-exam.start', $exam->share_token) }}" @class(['mt-6 space-y-4', 'hidden' => ! $errors->hasAny(['guest_name', 'guest_contact'])])>
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Your name') }}</label>
            <input type="text" name="guest_name" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            @error('guest_name')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Phone or email') }}</label>
            <input type="text" name="guest_contact" value="{{ $errors->has('result') ? '' : old('guest_contact') }}" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            <p class="mt-1 text-xs text-gray-500">{{ __('Write your name and phone/email correctly — you will need exactly these to see your answers later.') }}</p>
            @error('guest_contact')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="rounded-lg bg-amber-500 text-white px-4 py-2 font-medium hover:bg-amber-600">
            {{ __('Start exam') }}
        </button>
    </form>

    @include('guest-exam.partials.result-lookup', ['exam' => $exam])
@endsection
