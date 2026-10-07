@php
    $showsAnything = filled(strip_tags($html ?? '')) || filled($imageUrl);
@endphp

<div class="qb-question-preview">
    <p class="qb-question-preview-label">{{ __('Preview') }}</p>

    @if ($showsAnything)
        <div class="qb-question-preview-content">
            {!! $html !!}
        </div>

        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ __('Question image') }}" class="qb-question-preview-image">
        @endif
    @else
        <p class="qb-question-preview-empty">{{ __('Nothing written yet.') }}</p>
    @endif
</div>
