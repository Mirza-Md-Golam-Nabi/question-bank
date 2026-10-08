@php
    $subjects = $this->subjects();
@endphp

<x-filament-panels::page>
    <x-browse.grid :is-empty="$subjects->isEmpty()" :empty-text="__('No subjects attached to this class yet.')">
        @foreach ($subjects as $index => $classSubject)
            <x-browse.card
                :index="$index"
                :palette-offset="1"
                :title="$classSubject->subject->name"
                icon="heroicon-o-book-open"
                :href="$this->getResource()::getUrl('chapters', ['class' => $this->class->id, 'classSubject' => $classSubject->id])"
            >
                <x-browse.meta icon="heroicon-o-bookmark-square">
                    {{ trans_choice(':count Chapter|:count Chapters', $classSubject->chapters_count) }}
                </x-browse.meta>
                <x-browse.meta icon="heroicon-o-question-mark-circle">
                    {{ trans_choice(':count Question|:count Questions', $classSubject->questions_count) }}
                </x-browse.meta>

                @if ($this->canManageContent())
                    <x-slot name="menu">
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-arrows-up-down"
                            wire:click="mountAction('editSubjectOrder', { classSubject: {{ $classSubject->id }} })"
                        >
                            {{ __('Change order') }}
                        </x-filament::dropdown.list.item>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-x-mark"
                            color="danger"
                            wire:click="mountAction('detachSubject', { classSubject: {{ $classSubject->id }} })"
                        >
                            {{ __('Remove') }}
                        </x-filament::dropdown.list.item>
                    </x-slot>
                @endif
            </x-browse.card>
        @endforeach
    </x-browse.grid>
</x-filament-panels::page>
