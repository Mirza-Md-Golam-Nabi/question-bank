@php
    use App\Filament\Support\QuestionDisplay;

    $exam = $this->getRecord();
    $rows = $this->pageOfRows;
@endphp

<x-filament-panels::page>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total participants') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $this->rows->count() }}</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total marks') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ QuestionDisplay::marks($exam->total_marks) }}</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Highest marks') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">
                {{ $this->rows->isEmpty() ? '—' : QuestionDisplay::marks($this->rows->max('score')) }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Not submitted yet') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $this->pendingCount }}</p>
        </x-filament::section>
    </div>

    @if ($this->rows->isEmpty())
        <x-filament::empty-state
            :heading="__('Nobody has submitted this exam yet')"
            :description="__('Results appear here as soon as a student submits.')"
            icon="heroicon-o-users"
        />
    @else
        <x-filament::section>
            @include('teacher.partials.result-sheet-table', ['rows' => $rows, 'viewAction' => 'viewAnswers'])

            <div class="mt-4">
                <x-filament::pagination :paginator="$rows" />
            </div>
        </x-filament::section>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Position follows marks; equal marks share a position. A student who sat the exam more than once is counted on their first attempt.') }}
        </p>
    @endif
</x-filament-panels::page>
