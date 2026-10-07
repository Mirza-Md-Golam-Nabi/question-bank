<x-filament::section>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('Add a new question or create an exam') }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Add new questions to the question bank, or build an exam for your class and generate a share link.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->canAddQuestion())
                <x-filament::button tag="a" href="{{ $this->addQuestionUrl() }}" wire:navigate icon="heroicon-o-plus">
                    {{ __('Add question') }}
                </x-filament::button>
            @else
                <x-filament::button disabled color="gray">
                    {{ __('Add question') }}
                </x-filament::button>
            @endif

            <x-filament::button tag="a" href="{{ $this->createExamUrl() }}" wire:navigate color="gray" icon="heroicon-o-clipboard-document-list">
                {{ __('Create exam') }}
            </x-filament::button>
        </div>
    </div>
</x-filament::section>
