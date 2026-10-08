@php
    $chapters = $this->chapters();
@endphp

<x-filament-panels::page>
    <x-browse.grid
        :back-url="$this->getResource()::getUrl('subjects', ['class' => $this->class->id])"
        :back-label="__('Back to :name', ['name' => $this->class->name])"
        :is-empty="$chapters->isEmpty()"
        :empty-text="$this->canManageContent() ? __('No chapters yet. Add one to get started.') : __('No chapters yet.')"
    >
        @foreach ($chapters as $index => $chapter)
            <x-browse.card
                :index="$index"
                :palette-offset="3"
                :title="$chapter->name"
                icon="heroicon-o-bookmark-square"
                :href="$this->getResource()::getUrl('list', ['chapter' => $chapter->id])"
            >
                <x-browse.meta icon="heroicon-o-question-mark-circle">
                    {{ trans_choice(':count Question|:count Questions', $chapter->questions_count) }}
                </x-browse.meta>

                @if ($this->canManageContent())
                    <x-slot name="menu">
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-tag"
                            href="{{ $this->getResource()::getUrl('topics', ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $chapter->id]) }}"
                            tag="a"
                            wire:navigate
                        >
                            {{ __('Topics') }}
                        </x-filament::dropdown.list.item>
                        <x-browse.edit-delete-menu edit-action="editChapter" delete-action="deleteChapter" argument="chapter" :id="$chapter->id" />
                    </x-slot>
                @endif
            </x-browse.card>
        @endforeach
    </x-browse.grid>
</x-filament-panels::page>
