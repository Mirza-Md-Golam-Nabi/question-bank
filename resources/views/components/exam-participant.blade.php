@props(['attempt'])

{{-- Who an attempt belongs to — name on the left, contact on the right. The
     same line for a guest (the name and phone/email they typed) and for a
     logged-in student (their account's name and email). The text is smaller
     on a phone so both fit on one line there too; they wrap onto two lines
     only when even that can't fit them. Shown on the exam page and on a
     guest's result. --}}
<div {{ $attributes->class(['flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs sm:text-sm']) }}>
    <p><span class="text-gray-500">{{ __('Name') }}:</span> <span class="font-medium">{{ $attempt->participantName() }}</span></p>
    <p><span class="text-gray-500">{{ __('Phone/Email') }}:</span> <span class="font-medium">{{ $attempt->participantContact() }}</span></p>
</div>
