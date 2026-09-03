<x-filament-panels::page>
    @php $profile = $this->profile(); @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <span class="text-sm text-gray-500">Questions approved</span>
            <p class="text-2xl font-bold">{{ $profile?->total_questions_approved ?? 0 }}</p>
        </x-filament::section>
        <x-filament::section>
            <span class="text-sm text-gray-500">Total earned</span>
            <p class="text-2xl font-bold">৳{{ number_format($profile?->total_earned ?? 0, 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <span class="text-sm text-gray-500">Total paid</span>
            <p class="text-2xl font-bold">৳{{ number_format($profile?->total_paid ?? 0, 2) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section heading="Subject-wise breakdown">
        <table class="fi-ta-table w-full text-start">
            <thead>
                <tr>
                    <th class="p-2 text-start">Subject</th>
                    <th class="p-2 text-start">Approved questions</th>
                    <th class="p-2 text-start">Earned</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->subjectBreakdown() as $subject => $row)
                    <tr>
                        <td class="p-2">{{ $subject }}</td>
                        <td class="p-2">{{ $row['count'] }}</td>
                        <td class="p-2">৳{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-2" colspan="3">No earnings yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Bank / mobile banking info">
        <form wire:submit="saveBankInfo" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit">
                Save
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
