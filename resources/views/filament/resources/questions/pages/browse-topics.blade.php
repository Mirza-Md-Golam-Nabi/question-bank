@php
    $topics = $this->topics();
@endphp

<x-filament-panels::page>
    <x-browse.grid
        :back-url="$this->getResource()::getUrl('chapters', ['class' => $this->class->id, 'classSubject' => $this->classSubject->id])"
        :back-label="__('Back to :name', ['name' => $this->chapter->name])"
        :is-empty="$topics->isEmpty()"
        :empty-text="__('No topics yet. Add one to get started.')"
    >
        @foreach ($topics as $index => $topic)
            {{-- A topic has no page of its own to open, so the card is not a link. --}}
            <x-browse.card :index="$index" :palette-offset="3" :title="$topic->name" icon="heroicon-o-tag">
                <x-browse.meta icon="heroicon-o-question-mark-circle">
                    {{ trans_choice(':count Question|:count Questions', $topic->questions_count) }}
                </x-browse.meta>

                <x-slot name="menu">
                    <x-browse.edit-delete-menu edit-action="editTopic" delete-action="deleteTopic" argument="topic" :id="$topic->id" />
                </x-slot>
            </x-browse.card>
        @endforeach
    </x-browse.grid>
</x-filament-panels::page>
