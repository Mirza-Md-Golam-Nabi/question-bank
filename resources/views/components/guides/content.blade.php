@props([
    /** @var \App\Models\Guide $guide */
    'guide',
])

{{-- One guide as it is read — its video, then its steps — on the "Help" page
     and in a page's "How do I…?" panel alike. --}}
<article {{ $attributes->class('space-y-4') }}>
    @if ($embed = $guide->videoEmbedUrl())
        <div class="aspect-video overflow-hidden rounded-xl bg-black">
            <iframe
                src="{{ $embed }}"
                title="{{ $guide->title() }}"
                class="size-full"
                loading="lazy"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
    @endif

    <div class="fi-prose max-w-none">
        {!! $guide->bodyHtml() !!}
    </div>
</article>
