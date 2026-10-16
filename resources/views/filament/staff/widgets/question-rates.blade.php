<x-filament::section>
    <x-slot name="heading">
        {{ __('Current rates (per question)') }}
    </x-slot>

    <x-data-table compact :columns="[__('Subject') => 'start', __('Rate') => 'center']">
            @forelse ($this->rates() as $row)
                <tr>
                    <td>{{ $row['subject'] }}</td>
                    <td class="text-center">৳{{ number_format($row['rate'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">{{ __('No subjects found.') }}</td>
                </tr>
            @endforelse
    </x-data-table>
</x-filament::section>
