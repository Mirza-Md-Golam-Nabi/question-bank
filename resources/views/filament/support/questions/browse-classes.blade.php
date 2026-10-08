@php
    $classes = $this->classes();
@endphp

<x-filament-panels::page>
    <x-browse.grid
        :is-empty="$classes->isEmpty()"
        :empty-text="$this->canManageContent() ? __('No classes yet. Add one to get started.') : __('No classes yet.')"
    >
        @foreach ($classes as $index => $class)
            <x-browse.card
                :index="$index"
                :title="$class->name"
                icon="heroicon-o-rectangle-stack"
                :href="$this->getResource()::getUrl('subjects', ['class' => $class->id])"
            >
                <x-browse.meta icon="heroicon-o-book-open">
                    {{ trans_choice(':count Subject|:count Subjects', $class->class_subjects_count) }}
                </x-browse.meta>

                @if ($this->canManageContent())
                    <x-slot name="menu">
                        <x-browse.edit-delete-menu edit-action="editClass" delete-action="deleteClass" argument="class" :id="$class->id" />
                    </x-slot>
                @endif
            </x-browse.card>
        @endforeach
    </x-browse.grid>
</x-filament-panels::page>
