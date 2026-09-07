<x-filament-panels::page>
    @php
        // Light/pastel in light mode; a low-opacity tint (not a solid light
        // fill, which would look washed-out) in dark mode — with dark text
        // throughout, since light backgrounds + white text read poorly.
        $gradients = [
            'from-sky-100 to-blue-200 dark:from-sky-500/20 dark:to-blue-600/20',
            'from-emerald-100 to-teal-200 dark:from-emerald-500/20 dark:to-teal-600/20',
            'from-amber-100 to-orange-200 dark:from-amber-500/20 dark:to-orange-600/20',
            'from-fuchsia-100 to-purple-200 dark:from-fuchsia-500/20 dark:to-purple-600/20',
            'from-rose-100 to-pink-200 dark:from-rose-500/20 dark:to-pink-600/20',
            'from-lime-100 to-green-200 dark:from-lime-500/20 dark:to-green-600/20',
        ];

        // Randomly assigned per card rather than cycled by index — with a
        // 6-color palette on a 6-wide grid, `index % count($gradients)`
        // always lands the same color in the same column on every row,
        // which reads as a repetitive vertical stripe pattern (see the
        // screenshot this was reported from). Excluding the previous
        // card's color from the pool avoids two neighbors ever matching.
        $months = $this->monthlyEarnings();

        $previousGradient = null;
        $cardGradients = $months->map(function () use ($gradients, &$previousGradient) {
            $choices = array_values(array_diff($gradients, [$previousGradient]));

            return $previousGradient = $choices[array_rand($choices)];
        });
    @endphp

    <div class="grid grid-cols-3 gap-2 lg:grid-cols-6 lg:gap-3">
        @foreach ($months as $index => $month)
            @php $gradient = $cardGradients[$index]; @endphp

            <div class="relative overflow-hidden rounded-xl border border-gray-200/60 bg-gradient-to-br {{ $gradient }} p-3 shadow-sm dark:border-white/10 lg:rounded-2xl lg:p-4">
                <span class="block text-xs font-medium text-gray-600 dark:text-gray-300 lg:text-sm">{{ $month['label'] }}</span>
                <p class="mt-1 truncate text-sm font-bold text-gray-900 dark:text-white lg:text-lg">৳{{ number_format($month['total'], 2) }}</p>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
