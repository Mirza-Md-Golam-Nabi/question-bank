@extends('layouts.guest-exam')

@section('title', $attempt->exam->title)

@section('content')
    <h1 class="text-2xl font-bold mb-1">{{ $attempt->exam->title }}</h1>
    <p class="text-gray-500 mb-6">{{ $attempt->exam->duration_minutes }} minutes &middot; {{ $attempt->exam->total_marks }} marks</p>

    <form method="POST" action="{{ route('guest-exam.submit', $attempt) }}" class="space-y-8">
        @csrf

        @foreach ($attempt->exam->questions as $index => $question)
            <div class="border-t pt-4">
                <p class="font-medium mb-3">{{ $index + 1 }}. {!! $question->question_text !!}</p>

                @if ($question->question_type->value === 'mcq')
                    <div class="space-y-2">
                        @foreach ($question->options as $option)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option['option'] }}">
                                <span>{!! $option['option'] !!}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea name="answers[{{ $question->id }}]" rows="4" class="w-full rounded-lg border-gray-300"
                        placeholder="Write your answer..."></textarea>
                @endif
            </div>
        @endforeach

        <button type="submit" class="rounded-lg bg-amber-500 text-white px-4 py-2 font-medium hover:bg-amber-600">
            Submit exam
        </button>
    </form>
@endsection
