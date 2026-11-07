{{-- The form every self-practice page starts its exam with — or, before the student has a class, the way to set one. --}}
@if ($this->hasClass())
    <form wire:submit="start" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ $this->submitLabel() }}
        </x-filament::button>
    </form>
@else
    <x-filament::section icon="heroicon-o-academic-cap" icon-color="warning" :heading="__('Set your class first')">
        <p class="text-sm">{{ __('Practice exams are made from your own class. Choose your class on your profile to start.') }}</p>

        <x-filament::button tag="a" :href="$this->profileUrl()" class="mt-3">
            {{ __('Go to my profile') }}
        </x-filament::button>
    </x-filament::section>
@endif
