@extends('layouts.guest-exam')

@section('title', $exam->title)

@section('content')
    <h1 class="text-xl font-bold">{{ $exam->title }}</h1>
    <p class="text-gray-500 mt-2">{{ __('The class list of the school could not be loaded just now, so the exam cannot be started. Please try again in a few minutes, or tell your school.') }}</p>
@endsection
