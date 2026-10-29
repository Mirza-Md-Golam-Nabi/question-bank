@php
    /**
     * A question held by the background duplicate check, above the
     * questions already in its chapter it looks like — each with how alike
     * and its answers.
     *
     * @var \App\Models\Question $question
     * @var list<\App\Services\DuplicateMatch> $matches
     */
@endphp

<div class="space-y-4" x-data x-init="window.renderKatexEmbeds?.($el)">
    <section class="space-y-2">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('The new question') }}</h3>

        @include('filament.support.questions.question-body', ['question' => $question, 'showAnswers' => true])
    </section>

    <section class="space-y-3">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Questions already there it looks like') }}</h3>

        @forelse ($matches as $match)
            <x-question-duplicate :match="$match" />
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('The questions it looked like are no longer there.') }}</p>
        @endforelse
    </section>
</div>
