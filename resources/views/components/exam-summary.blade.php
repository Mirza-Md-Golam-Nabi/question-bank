@props(['exam'])

{{-- "30 minutes · 25 marks" — the one-line summary shown wherever an exam is
     introduced (the share-link page, the exam paper's top bar). --}}
{{ __(':minutes minutes', ['minutes' => $exam->duration_minutes]) }} &middot; {{ __(':marks marks', ['marks' => $exam->total_marks]) }}
