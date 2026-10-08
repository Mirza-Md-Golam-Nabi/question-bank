@props([
    'editAction',
    'deleteAction',
    'argument',
    'id',
])

{{-- The "Edit" / "Delete" entries of a browser card's menu: each mounts the
     page's action of that name, passing the item's id as `argument`. --}}
<x-filament::dropdown.list.item
    icon="heroicon-o-pencil-square"
    wire:click="mountAction('{{ $editAction }}', { {{ $argument }}: {{ $id }} })"
>
    {{ __('Edit') }}
</x-filament::dropdown.list.item>

<x-filament::dropdown.list.item
    icon="heroicon-o-trash"
    color="danger"
    wire:click="mountAction('{{ $deleteAction }}', { {{ $argument }}: {{ $id }} })"
>
    {{ __('Delete') }}
</x-filament::dropdown.list.item>
