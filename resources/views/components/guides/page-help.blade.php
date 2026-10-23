@props([
    /** @var \Illuminate\Support\Collection<int, \App\Models\Guide> $guides */
    'guides',
])

{{-- A page's "How do I…?" button (PanelDefaults): its guides for this
     user's role open beside the page, one after another, without leaving it. --}}
<x-filament::modal slide-over width="2xl" :heading="__('How do I…?')">
    <x-slot name="trigger">
        <x-filament::button color="gray" icon="heroicon-o-question-mark-circle" size="sm">
            {{ __('How do I…?') }}
        </x-filament::button>
    </x-slot>

    <div class="space-y-8">
        @foreach ($guides as $guide)
            <section class="space-y-3">
                @if ($guides->count() > 1)
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $guide->title() }}</h3>
                @endif

                <x-guides.content :guide="$guide" />
            </section>
        @endforeach

        <a href="{{ \App\Filament\Support\Pages\HelpCenter::getUrl() }}" class="inline-block text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
            {{ __('All guides') }} →
        </a>
    </div>
</x-filament::modal>
