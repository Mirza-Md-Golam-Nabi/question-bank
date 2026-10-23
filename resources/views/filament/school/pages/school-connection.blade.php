@php
    $profile = $this->profile;

    // What the operator copies into the school management software, in the
    // order its settings page asks for them.
    $fields = [
        ['label' => __('Question Bank address'), 'value' => $this->platformUrl(), 'secret' => false],
        ['label' => __('Connection code'), 'value' => $profile->connection_code, 'secret' => false],
        ['label' => __('Secret'), 'value' => $profile->connection_secret, 'secret' => true],
    ];
@endphp

<x-filament-panels::page>
    <x-filament::section
        :icon="$profile->isConnected() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'"
        :icon-color="$profile->isConnected() ? 'success' : 'warning'"
        :heading="$profile->isConnected() ? __('Connected') : __('Not connected yet')"
    >
        <p class="text-sm text-gray-600 dark:text-gray-400">
            @if ($profile->isConnected())
                {{ __('Your school management software at :url connected on :date.', ['url' => $profile->installation_url, 'date' => $profile->connected_at->translatedFormat('j M Y, g:i A')]) }}
            @else
                {{ __('In your school management software, open School Settings → Question Bank, enter the three values below and press "Save and connect".') }}
            @endif
        </p>
    </x-filament::section>

    <x-filament::section :heading="__('Connection details')">
        <div class="space-y-4">
            @foreach ($fields as $field)
                <div x-data="{ shown: @js(! $field['secret']), copied: false }">
                    <p class="mb-1 text-sm font-medium text-gray-950 dark:text-white">{{ $field['label'] }}</p>

                    <div class="flex flex-wrap items-center gap-2">
                        <code
                            class="min-w-0 flex-1 break-all rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-950 dark:bg-white/5 dark:text-white"
                            x-text="shown ? @js($field['value']) : '••••••••••••••••••••••••'"
                        ></code>

                        @if ($field['secret'])
                            <x-filament::button size="sm" color="gray" x-on:click="shown = ! shown">
                                <span x-text="shown ? @js(__('Hide')) : @js(__('Show'))"></span>
                            </x-filament::button>
                        @endif

                        <x-filament::button
                            size="sm"
                            color="gray"
                            icon="heroicon-o-clipboard-document"
                            x-on:click="navigator.clipboard.writeText(@js($field['value'])); copied = true; setTimeout(() => copied = false, 2000)"
                        >
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></span>
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Keep the secret to your school: whoever has it can act as your school between the two systems.') }}
        </p>

        <div class="mt-4">
            {{ $this->regenerateSecretAction }}
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
