<x-filament-panels::page>
    @php $request = $this->latestRequest(); @endphp

    @if ($request)
        <x-filament::section :heading="__('Your latest request')">
            <p class="text-sm">
                <span class="text-gray-500">{{ __('Status') }}:</span>
                <span class="font-bold">{{ $request->status->getLabel() }}</span>
                <span class="text-gray-500">· {{ $request->created_at->format('M j, Y') }}</span>
            </p>
            <p class="mt-2 whitespace-pre-line text-sm">{{ $request->message }}</p>

            @if (filled($request->admin_note))
                <p class="mt-3 text-sm">
                    <span class="text-gray-500">{{ __('Reply from the admin') }}:</span> {{ $request->admin_note }}
                </p>
            @endif
        </x-filament::section>
    @endif

    @if ($this->canSubmit())
        <x-filament::section :heading="__('Request reactivation')">
            <form wire:submit="submit" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit">
                    {{ __('Send request') }}
                </x-filament::button>
            </form>
        </x-filament::section>
    @elseif ($request?->isPending())
        <p class="text-sm text-gray-500">{{ __('Your reactivation request is waiting for an admin.') }}</p>
    @endif
</x-filament-panels::page>
