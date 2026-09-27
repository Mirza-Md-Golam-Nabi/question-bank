<x-filament::section>
    <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" />
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('আপনার একাউন্ট সাসপেন্ড করা হয়েছে') }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('আপনি Dashboard ও আপনার earning দেখতে পারবেন, কিন্তু নতুন প্রশ্ন যোগ করতে পারবেন না। বিস্তারিত জানতে Admin-এর সাথে যোগাযোগ করুন।') }}
            </p>
        </div>
    </div>
</x-filament::section>
