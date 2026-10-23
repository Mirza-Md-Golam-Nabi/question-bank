<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-clock" icon-color="warning" :heading="__('Your school account is awaiting approval')">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Tell us which school this account is for. An admin checks these details and approves the account; then you can connect your school management software and set exams for your students.') }}
        </p>
    </x-filament::section>

    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('Save') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
