<x-filament-panels::page>
    <x-filament::section>
        <p class="text-lg">
            {{ __('Score') }}: <span class="font-bold">{{ $attempt->total_score }}</span> / {{ $attempt->exam->total_marks }}
        </p>
    </x-filament::section>

    @if ($attempt->exam->showsAnswersToStudents())
        @php
            $answersByQuestion = $attempt->answers->keyBy('question_id');
        @endphp

        <div class="space-y-4 mt-4">
            @foreach ($attempt->shuffledQuestions() as $index => $question)
                <x-filament::section>
                    <x-exam-result-question
                        :question="$question"
                        :answer="$answersByQuestion->get($question->id)"
                        :number="$index + 1"
                    />
                </x-filament::section>
            @endforeach
        </div>
    @else
        {{-- Only the score for now: showing the answers while the exam is
             still open would hand them to everyone who has yet to sit it. --}}
        <x-filament::section class="mt-4">
            <p class="font-medium">{{ __('The answers are not available yet.') }}</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Your teacher will release them later. Open this exam again from the "My results" page to see the correct answers alongside your own.') }}
            </p>
        </x-filament::section>
    @endif

    <div class="mt-6 flex justify-center">
        <x-filament::button tag="a" :href="\Filament\Pages\Dashboard::getUrl()" color="gray" icon="heroicon-o-x-mark">
            {{ __('Close') }}
        </x-filament::button>
    </div>
</x-filament-panels::page>
