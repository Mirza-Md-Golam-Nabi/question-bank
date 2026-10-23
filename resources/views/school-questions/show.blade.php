@extends('layouts.guest-exam')

{{-- A School's own questions, open to its students (SchoolQuestionsController),
     inside a frame of the school's software — which picks the language. --}}

@section('title', __('School questions'))

@section('hide-language-switcher', true)

@section('hide-home-link', true)

@section('content')
    <h1 class="text-xl font-bold">{{ __('School questions') }}</h1>
    <p class="text-sm text-gray-500 mt-1">{{ __('Questions :school has used in its exams, with the answers.', ['school' => $schoolName]) }}</p>

    @if ($classSubjects->isEmpty())
        <p class="mt-6 text-gray-600">{{ __('No questions are open yet. They open the day after the exams they were on are over.') }}</p>
    @else
        <form method="GET" action="{{ url()->current() }}" class="mt-5 grid gap-3 sm:grid-cols-2">
            @foreach ($linkQuery as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            <label class="block text-sm">
                <span class="font-medium">{{ __('Class and subject') }}</span>
                <select name="subject" class="mt-1 block w-full rounded border-gray-300" onchange="this.form.chapter.value = ''; this.form.submit()">
                    @foreach ($classSubjects as $classSubject)
                        <option value="{{ $classSubject['id'] }}" @selected($classSubject['id'] === $classSubjectId)>{{ $classSubject['label'] }} ({{ $classSubject['count'] }})</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm">
                <span class="font-medium">{{ __('Chapter') }}</span>
                <select name="chapter" class="mt-1 block w-full rounded border-gray-300" onchange="this.form.submit()">
                    <option value="">{{ __('All chapters') }}</option>
                    @foreach ($chapters as $chapter)
                        <option value="{{ $chapter['id'] }}" @selected($chapter['id'] === $chapterId)>{{ $chapter['name'] }} ({{ $chapter['count'] }})</option>
                    @endforeach
                </select>
            </label>

            <noscript><button type="submit" class="rounded bg-gray-800 px-3 py-2 text-sm text-white">{{ __('Show') }}</button></noscript>
        </form>

        <div class="mt-6">
            @include('filament.support.questions.question-list', ['mcqQuestions' => $mcqQuestions, 'cqQuestions' => $cqQuestions])
        </div>

        @if ($questions->hasPages())
            <div class="mt-6">{{ $questions->links() }}</div>
        @endif
    @endif
@endsection
