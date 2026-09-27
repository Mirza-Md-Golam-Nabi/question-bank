<x-filament::section>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('নতুন প্রশ্ন যোগ করতে চান?') }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Class → Subject → Chapter বেছে নিয়ে প্রশ্ন যোগ করুন।') }}
            </p>
        </div>

        @if ($this->isStaffSuspended())
            <x-filament::button disabled color="gray">
                {{ __('প্রশ্ন যোগ করুন') }}
            </x-filament::button>
        @else
            <x-filament::button tag="a" href="{{ $this->addQuestionUrl() }}" wire:navigate icon="heroicon-o-plus">
                {{ __('প্রশ্ন যোগ করুন') }}
            </x-filament::button>
        @endif
    </div>
</x-filament::section>
