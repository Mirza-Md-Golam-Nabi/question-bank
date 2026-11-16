@php
    /**
     * One set of alike approved questions (ApprovedDuplicates): where it
     * is, how many, then every question whole — its answer and explanation
     * — with how alike it is, who wrote it and how much it is used, so the
     * Admin can tell which to keep.
     *
     * @var \App\Models\ApprovedDuplicateSet $set
     */
    $set = $getRecord();
@endphp

<div class="w-full space-y-3 py-1" x-data x-init="window.renderKatexEmbeds?.($el)">
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge color="danger" icon="heroicon-m-square-2-stack">
            {{ __(':count alike questions', ['count' => $set->members->count()]) }}
        </x-filament::badge>

        <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ $set->chapter?->classSubject?->label() }} · {{ $set->chapter?->name }}
        </span>
    </div>

    {{-- Side by side only where each still has room for its options. --}}
    <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,26rem),1fr))] gap-3">
        @foreach ($set->members as $member)
            @php
                $question = $member->question;
            @endphp

            <div class="space-y-2 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $loop->iteration }}.</span>

                    <x-filament::badge :color="$member->is_exact ? 'danger' : 'warning'">
                        {{ $member->is_exact ? __('The same question') : __(':percent% alike', ['percent' => $member->percent]) }}
                    </x-filament::badge>

                    <span>{{ __(':count exams', ['count' => $question->exams_count]) }}</span>
                    <span>·</span>
                    <span>{{ __(':count answers', ['count' => (int) $question->answer_count]) }}</span>
                    <span>·</span>
                    <span>{{ \App\Enums\Locale::current()->writeDigits((string) $question->created_at?->translatedFormat('M j, Y')) }}</span>
                </div>

                @include('filament.support.pages.question-picker.question-card', ['question' => $question, 'showAnswers' => true, 'reviewing' => true])
            </div>
        @endforeach
    </div>
</div>
