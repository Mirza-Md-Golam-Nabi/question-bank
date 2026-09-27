<x-filament::section>
    <x-slot name="heading">
        {{ __('বর্তমান রেট (প্রতি প্রশ্ন)') }}
    </x-slot>

    <table class="fi-ta-table w-full text-start [&_*]:text-xs! lg:[&_*]:text-sm!">
        <thead>
            <tr>
                <th class="p-2 text-start">{{ __('Subject') }}</th>
                <th class="p-2 text-center">{{ __('Rate') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->rates() as $row)
                <tr>
                    <td class="p-2">{{ $row['subject'] }}</td>
                    <td class="p-2 text-center">৳{{ number_format($row['rate'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="p-2" colspan="2">{{ __('কোনো সাবজেক্ট পাওয়া যায়নি।') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-filament::section>
