{{--
    All / Student / Teacher tabs above the plans table. Every plan is already
    on the page (the table isn't paginated), so switching only hides rows in
    the browser — no request, no query. The choice sits on <body>, outside
    what Livewire redraws, so the table keeps it after an edit or a delete;
    the rows to hide are matched in question-display.css (.qb-plan-role-*).
--}}
<div
    x-data="{
        role: 'all',
        show(role) {
            this.role = role
            document.body.dataset.qbPlanRole = role

            // Nothing hidden may stay ticked for a bulk action.
            document
                .querySelectorAll('[x-data^=filamentTable]')
                .forEach((table) => Alpine.$data(table).deselectAllRecords?.())
        },
    }"
    x-init="document.body.dataset.qbPlanRole = role"
    x-on:livewire:navigating.window="delete document.body.dataset.qbPlanRole"
>
    <x-filament::tabs>
        @foreach ($tabs as $value => $label)
            <x-filament::tabs.item
                :alpine-active="'role === ' . \Illuminate\Support\Js::from($value)"
                x-on:click="show({{ \Illuminate\Support\Js::from($value) }})"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</div>
