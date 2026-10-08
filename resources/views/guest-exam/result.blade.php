@extends('layouts.guest-exam')

@section('title', __('Result').' — '.$attempt->exam->title)

@section('hide-language-switcher', true)

@section('content')
    <h1 class="text-2xl font-bold mb-1">{{ $attempt->exam->title }}</h1>
    <x-exam-participant :attempt="$attempt" class="mb-4" />

    <x-exam-result :attempt="$attempt">
        {{ __('Your teacher will release them later. Come back to this exam link then and enter the same name and phone/email to see the correct answers alongside your own.') }}
    </x-exam-result>

    {{-- A guest has no account to go back to, so closing leads home. --}}
    <div class="mt-8 border-t pt-6 text-center">
        <a href="{{ route('home') }}" class="qb-btn qb-btn--primary">{{ __('Close') }}</a>
    </div>
@endsection
