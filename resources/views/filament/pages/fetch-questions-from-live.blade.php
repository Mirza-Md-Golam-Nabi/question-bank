<x-filament-panels::page>
    {{-- Whether the live server can be asked now: its sharing open (until when), or why not. --}}
    @if ($connectionProblem)
        <x-filament::callout color="danger" icon="heroicon-o-exclamation-triangle" :heading="$connectionProblem">
            <x-slot name="controls">
                <x-filament::button size="sm" color="gray" wire:click="checkConnection">{{ __('Try again') }}</x-filament::button>
            </x-slot>
        </x-filament::callout>
    @else
        <x-filament::callout color="success" icon="heroicon-o-check-circle" :heading="__('Connected to the live server. Its sharing is open until :time.', ['time' => $openUntil])" />
    @endif

    <x-filament::section :heading="__('A chapter of the question bank')" :description="__('Every approved question of the live chapter comes into the test chapter, approved. A question already fetched is updated only when it has a new version there; topics are found by name, or made.')">
        <form wire:submit="fetchChapter" class="space-y-4">
            {{ $this->chapterForm }}

            <x-filament::button type="submit" icon="heroicon-o-cloud-arrow-down">
                {{ __('Fetch') }}
            </x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section :heading="__('A past paper')" :description="__('The whole paper with its MCQs, CQs, answers and explanations, approved. Fetching it again replaces its questions, unless someone has sat it here.')">
        <form wire:submit="fetchPaper" class="space-y-4">
            {{ $this->paperForm }}

            <x-filament::button type="submit" icon="heroicon-o-cloud-arrow-down">
                {{ __('Fetch') }}
            </x-filament::button>
        </form>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
