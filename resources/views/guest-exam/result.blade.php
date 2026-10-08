@extends('layouts.guest-exam')

@section('title', __('Result').' — '.$attempt->exam->title)

@section('hide-language-switcher', true)

@section('content')
    <h1 class="text-2xl font-bold mb-1">{{ $attempt->exam->title }}</h1>
    <p class="text-sm text-gray-500 mb-2">{{ $attempt->guest_name }}</p>
    <p class="text-lg mb-6">
        {{ __('Score') }}: <span class="font-bold">{{ $attempt->total_score }}</span> / {{ $attempt->exam->total_marks }}
    </p>

    @if ($attempt->exam->showsAnswersToStudents())
        @php
            $answersByQuestion = $attempt->answers->keyBy('question_id');
        @endphp

        <div class="space-y-4">
            @foreach ($attempt->shuffledQuestions() as $index => $question)
                <x-exam-result-question
                    class="border-t pt-4"
                    :question="$question"
                    :answer="$answersByQuestion->get($question->id)"
                    :number="$index + 1"
                />
            @endforeach
        </div>
    @else
        {{-- Only the score for now: showing the answers while the exam is
             still open would hand them to everyone who has yet to sit it. --}}
        <div class="rounded-lg bg-amber-50 px-4 py-3 text-amber-900">
            <p class="font-medium">{{ __('The answers are not available yet.') }}</p>
            <p class="mt-1 text-sm">{{ __('Your teacher will release them later. Come back to this exam link then and enter the same name and phone/email to see the correct answers alongside your own.') }}</p>
        </div>
    @endif

    {{-- A guest has no account to go back to, so closing leads home. --}}
    <div class="mt-8 border-t pt-6 text-center">
        <a href="{{ route('home') }}"
           class="inline-block rounded-lg bg-amber-500 px-6 py-2 font-medium text-white hover:bg-amber-600">
            {{ __('Close') }}
        </a>
    </div>
@endsection
