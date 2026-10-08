@props([
    'idPrefix',
    'refill' => false,
])

@php
    /**
     * The two fields that identify a guest — name and phone/email — asked
     * once to start an exam and again to look the result up. The slot is an
     * optional hint under the contact field.
     *
     * @var string $idPrefix  Keeps the field ids unique when both forms are on one page.
     * @var bool $refill      Put back what was typed after a failed submit of this form.
     */
@endphp

<div>
    <label class="qb-label" for="{{ $idPrefix }}-name">{{ __('Your name') }}</label>
    <input id="{{ $idPrefix }}-name" type="text" name="guest_name" value="{{ $refill ? old('guest_name') : '' }}" required class="qb-input">
</div>

<div>
    <label class="qb-label" for="{{ $idPrefix }}-contact">{{ __('Phone or email') }}</label>
    <input id="{{ $idPrefix }}-contact" type="text" name="guest_contact" value="{{ $refill ? old('guest_contact') : '' }}" required class="qb-input">

    @if ($slot->isNotEmpty())
        <p class="mt-1 text-xs text-gray-500">{{ $slot }}</p>
    @endif
</div>
