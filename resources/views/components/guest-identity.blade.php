@props(['attempt'])

{{-- Who a guest attempt belongs to — name on the left, contact on the right.
     The text is smaller on a phone so both fit on one line there too; they
     wrap onto two lines only when even that can't fit them.
     Shown on the guest's exam page and on their result. --}}
<div {{ $attributes->class(['flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs sm:text-sm']) }}>
    <p><span class="text-gray-500">{{ __('Name') }}:</span> <span class="font-medium">{{ $attempt->guest_name }}</span></p>
    <p><span class="text-gray-500">{{ __('Phone/Email') }}:</span> <span class="font-medium">{{ $attempt->guest_contact }}</span></p>
</div>
