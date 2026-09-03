@extends('layouts.guest-exam')

@section('title', 'Result — '.$attempt->exam->title)

@section('content')
    <h1 class="text-2xl font-bold mb-1">{{ $attempt->exam->title }}</h1>
    <p class="text-lg mb-6">
        Score: <span class="font-bold">{{ $attempt->total_score }}</span> / {{ $attempt->exam->total_marks }}
    </p>

    <div class="space-y-4">
        @foreach ($attempt->answers as $answer)
            <div class="border-t pt-4">
                <p class="font-medium mb-1">{!! $answer->question->question_text !!}</p>
                @if ($answer->is_correct !== null)
                    <p class="text-sm {{ $answer->is_correct ? 'text-green-600' : 'text-red-600' }}">
                        {{ $answer->is_correct ? 'Correct' : 'Incorrect' }} — {{ $answer->obtained_marks }} marks
                    </p>
                @else
                    <p class="text-sm text-gray-500">Pending manual grading</p>
                @endif
            </div>
        @endforeach
    </div>
@endsection
