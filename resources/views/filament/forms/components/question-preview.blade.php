@php
    $showsAnything = filled(strip_tags($html ?? '')) || filled($imageUrl);
@endphp

<div class="qb-question-preview">
    <p class="qb-question-preview-label">প্রিভিউ</p>

    @if ($showsAnything)
        <div class="qb-question-preview-content">
            {!! $html !!}
        </div>

        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="প্রশ্নের ছবি" class="qb-question-preview-image">
        @endif
    @else
        <p class="qb-question-preview-empty">এখনো কিছু লেখা হয়নি।</p>
    @endif
</div>
