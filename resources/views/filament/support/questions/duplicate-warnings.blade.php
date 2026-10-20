@php
    /**
     * In the Admin's approve window: the questions already in the chapter
     * that this one (or each question of its group) looks like. Nothing
     * when it looks like none.
     *
     * @var list<\App\Services\DuplicateMatch> $matches
     */
@endphp

@if ($matches !== [])
    <div class="space-y-3" x-data x-init="window.renderKatexEmbeds?.($el)">
        @foreach ($matches as $match)
            <x-question-duplicate :match="$match" />
        @endforeach
    </div>
@endif
