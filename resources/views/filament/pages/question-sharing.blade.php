<x-filament-panels::page>
    @php
        $openUntil = $this->openUntil();
    @endphp

    @if (! $this->hasSecret())
        <x-filament::callout color="danger" icon="heroicon-o-exclamation-triangle" :heading="__("QUESTION_SYNC_SECRET is not set in this server's .env, so the sharing can't be opened.")" />
    @elseif ($openUntil)
        <x-filament::callout color="success" icon="heroicon-o-lock-open" :heading="__('Open until :time — the test server can fetch questions now.', ['time' => $openUntil])" />
    @else
        <x-filament::callout color="gray" icon="heroicon-o-lock-closed" :heading="__('Closed — the test server gets nothing from this server.')" />
    @endif

    <x-filament::section :heading="__('Setting up the test server')">
        <div class="space-y-2 text-sm">
            <p>{{ __("In the test server's .env:") }}</p>
            <pre class="overflow-x-auto rounded-lg bg-gray-950/5 p-3 text-xs dark:bg-white/5">QUESTION_SYNC_ROLE=consumer
QUESTION_SYNC_SOURCE_URL={{ config('app.url') }}
QUESTION_SYNC_SECRET=({{ __('the same secret as here') }})</pre>
            <p>{{ __('Only approved questions and approved past papers are shared, never anything else. Nothing comes back from the test server.') }}</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
