<x-filament::section>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('Want to add a new question?') }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Pick a Class → Subject → Chapter and add your question.') }}
            </p>
        </div>

        @if ($this->isStaffSuspended())
            <x-filament::button disabled color="gray">
                {{ __('Add question') }}
            </x-filament::button>
        @else
            <x-filament::button tag="a" href="{{ $this->addQuestionUrl() }}" wire:navigate icon="heroicon-o-plus">
                {{ __('Add question') }}
            </x-filament::button>
        @endif
    </div>
</x-filament::section>
