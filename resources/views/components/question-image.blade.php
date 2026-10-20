@props(['path', 'option' => false])

@php
    /**
     * A figure of a question — its own, its stimulus's, a sub-question's, or
     * an option's (`option`: drawn smaller, as it sits in a row of options).
     * Nothing when there is none. Every view that shows a question draws
     * its figures with this.
     *
     * @var string|null $path  Where it is kept on the public disk.
     */
    $url = \App\Filament\Support\QuestionDisplay::imageUrl($path);
@endphp

@if ($url)
    <img src="{{ $url }}" alt="" loading="lazy" {{ $attributes->class(['qb-question-image', 'qb-question-image--option' => $option]) }}>
@endif
