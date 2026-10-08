@extends('layouts.guest-exam')

@section('title', __('Exam not available'))

@section('content')
    <h1 class="text-xl font-bold">{{ __('This exam link is no longer active.') }}</h1>
    <p class="text-gray-500 mt-2">{{ __('It may have expired or been deactivated by the teacher.') }}</p>

    {{-- The link no longer starts attempts, but results stay reachable. --}}
    @if ($exam ?? null)
        @include('guest-exam.partials.result-lookup', ['exam' => $exam])
    @endif
@endsection
