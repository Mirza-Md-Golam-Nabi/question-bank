<x-filament-panels::page>
    <x-filament::section>
        <x-exam-result :attempt="$attempt">
            {{ __('Your teacher will release them later. Open this exam again from the "My results" page to see the correct answers alongside your own.') }}
        </x-exam-result>
    </x-filament::section>

    <div class="mt-6 flex justify-center">
        <x-filament::button tag="a" :href="\Filament\Pages\Dashboard::getUrl()" color="gray" icon="heroicon-o-x-mark">
            {{ __('Close') }}
        </x-filament::button>
    </div>
</x-filament-panels::page>
