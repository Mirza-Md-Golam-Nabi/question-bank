<x-filament-panels::page>
    @php
        $progress = $this->getProgress();
    @endphp

    {{-- While the check runs, how far it is — redrawn every few seconds, the sets found joining the list. --}}
    @if ($progress)
        <div wire:poll.5s class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200">
            {{ __('Looking through the approved questions: :done of :total chapters done.', ['done' => $progress['done'], 'total' => $progress['total']]) }}
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
