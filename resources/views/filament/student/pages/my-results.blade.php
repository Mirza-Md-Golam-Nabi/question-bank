<x-filament-panels::page>
    @if ($this->attempts->isEmpty())
        <x-filament::empty-state
            :heading="__('No exams submitted yet')"
            :description="__('Your results will appear here after you submit an exam.')"
            icon="heroicon-o-clipboard-document-check"
        />
    @else
        <div class="space-y-3">
            @foreach ($this->attempts as $attempt)
                <a
                    href="{{ $this->resultUrl($attempt) }}"
                    wire:key="attempt-{{ $attempt->id }}"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-950 dark:text-white">{{ $attempt->exam->title }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $attempt->submitted_at?->translatedFormat('j M Y, g:i A') }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-filament::badge :color="$attempt->exam->showsAnswersToStudents() ? 'success' : 'gray'">
                            {{ $attempt->exam->showsAnswersToStudents() ? __('Answers available') : __('Answers not released yet') }}
                        </x-filament::badge>

                        <span class="font-semibold text-gray-950 dark:text-white">
                            {{ $attempt->total_score }} / {{ $attempt->exam->total_marks }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <x-filament::pagination :paginator="$this->attempts" />
    @endif
</x-filament-panels::page>
