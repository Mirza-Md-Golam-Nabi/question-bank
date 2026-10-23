@extends('layouts.guest-exam')

@section('title', __('Exam not available'))

@section('content')
    <h1 class="text-xl font-bold">{{ __('This exam link is no longer active.') }}</h1>
    <p class="text-gray-500 mt-2">{{ __('It may have expired or been deactivated by the teacher.') }}</p>

    {{-- The link no longer starts attempts, but results stay reachable —
         a School's exam by class and roll, any other by a guest's name. --}}
    @if ($exam ?? null)
        @if ($exam->isSchoolExam())
            @if ($schoolClasses)
                @include('guest-exam.partials.school-result-lookup', ['exam' => $exam, 'classes' => $schoolClasses])
            @endif
        @else
            @include('guest-exam.partials.result-lookup', ['exam' => $exam])
        @endif
    @endif
@endsection
