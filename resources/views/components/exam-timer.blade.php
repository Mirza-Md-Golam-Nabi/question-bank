@props([
    'seconds',
])

@php
    /**
     * The bar pinned to the top of an exam page (guest and Student panel
     * alike): the exam's details on the left (the slot), the countdown on
     * the right. The page's own script drives the countdown through the
     * data-qb-timer-* hooks; this is only the markup.
     *
     * @var int $seconds  Seconds left when the page was rendered.
     */
@endphp

<div {{ $attributes->class(['qb-exam-bar']) }}>
    <div class="qb-exam-bar-info">{{ $slot }}</div>

    <div class="qb-exam-timer" data-qb-timer data-seconds="{{ $seconds }}" role="timer" aria-live="off">
        <span class="qb-exam-timer-label">{{ __('Time left') }}</span>
        <span class="qb-exam-timer-clock" data-qb-timer-clock>--:--</span>
    </div>
</div>

<p class="qb-exam-timeup" data-qb-timer-timeup hidden role="alert">
    {{ __('Time is up. You can no longer answer or change anything — just submit the exam.') }}
</p>
