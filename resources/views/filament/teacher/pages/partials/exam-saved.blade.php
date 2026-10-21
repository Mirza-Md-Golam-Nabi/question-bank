@php
    /**
     * The Teacher's question picker after the exam is saved: print it, go
     * and publish it, or start on another one.
     */
    $savedExam = $this->savedExam;
@endphp

<x-filament::section>
    <x-slot name="heading">{{ $this->editingExam ? __('Exam updated') : __('Exam saved') }}</x-slot>

    @if ($savedExam)
        <x-slot name="description">
            {{ $savedExam->title }} &middot;
            {{ trans_choice(':count Question|:count Questions', $savedExam->questions_count) }}
        </x-slot>

        <div class="flex flex-wrap gap-3">
            @if ($savedExam->delivery_mode->includesOffline())
                <x-filament::button
                    tag="a"
                    :href="route('filament.teacher.exams.print', $savedExam)"
                    target="_blank"
                    icon="heroicon-o-printer"
                >
                    {{ __('Print / PDF') }}
                </x-filament::button>
            @endif

            @if ($savedExam->delivery_mode->includesOnline())
                <x-filament::button tag="a" :href="$this->examsUrl()" icon="heroicon-o-globe-alt" color="success">
                    {{ __('Go to exams to publish it online') }}
                </x-filament::button>
            @endif

            <x-filament::button color="gray" wire:click="startNewSelection" icon="heroicon-o-plus">
                {{ __('Select questions for another exam') }}
            </x-filament::button>
        </div>
    @endif
</x-filament::section>
