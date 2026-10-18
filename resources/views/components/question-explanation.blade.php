@props(['text', 'title' => null])

@php
    /**
     * The explanation of an answer — a past paper MCQ's, or a CQ
     * sub-question's answer. Nothing when none was written.
     *
     * @var string|null $text
     */
@endphp

@if (filled($text))
    <div {{ $attributes->class('qb-question-explanation') }}>
        <p class="qb-question-explanation-title">{{ $title ?? __('Explanation') }}</p>
        <div class="qb-question-text">{!! $text !!}</div>
    </div>
@endif
