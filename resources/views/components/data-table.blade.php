{{--
    A plain read-only table for a page or widget that isn't a Filament
    table: the header row from `columns` (label => 'start'|'center'), the
    rows from the slot. Cell spacing and alignment come from .qb-data-table
    (question-display.css), so a row only writes its content.
--}}
@props([
    'columns',
    // Smaller text on a phone, for a narrow widget.
    'compact' => false,
])

<div {{ $attributes->class('overflow-x-auto') }}>
    <table @class(['fi-ta-table qb-data-table', 'qb-data-table--compact' => $compact])>
        <thead>
            <tr>
                @foreach ($columns as $label => $align)
                    <th @class(['text-center' => $align === 'center'])>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
