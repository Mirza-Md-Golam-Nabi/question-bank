<x-filament-panels::page>
    <x-filament::section>
        <p class="text-lg">
            Score: <span class="font-bold">{{ $attempt->total_score }}</span> / {{ $attempt->exam->total_marks }}
        </p>
    </x-filament::section>

    <div class="space-y-4 mt-4">
        @foreach ($attempt->answers as $answer)
            <x-filament::section>
                <p class="font-medium mb-1">{!! $answer->question->question_text !!}</p>
                @if ($answer->is_correct !== null)
                    <p class="text-sm {{ $answer->is_correct ? 'text-green-600' : 'text-red-600' }}">
                        {{ $answer->is_correct ? 'Correct' : 'Incorrect' }} — {{ $answer->obtained_marks }} marks
                    </p>
                @else
                    <p class="text-sm text-gray-500">Pending manual grading</p>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
