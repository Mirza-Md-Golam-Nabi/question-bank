<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-8">
        @foreach ($attempt->exam->questions as $index => $question)
            <div class="border-t pt-4">
                <p class="font-medium mb-3">{{ $index + 1 }}. {!! $question->question_text !!}</p>

                @if ($question->question_type->value === 'mcq')
                    <div class="space-y-2">
                        @foreach ($question->options as $option)
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option['option'] }}">
                                <span>{{ $option['option'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea wire:model="answers.{{ $question->id }}" rows="4" class="fi-input w-full rounded-lg border-gray-300"
                        placeholder="Write your answer..."></textarea>
                @endif
            </div>
        @endforeach

        <x-filament::button type="submit">
            Submit exam
        </x-filament::button>
    </form>
</x-filament-panels::page>
