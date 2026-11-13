@props(['data'])

<figure class="pb-block pb-block--quote">
    <blockquote class="pb-quote"><p>{{ $data['text'] ?? '' }}</p></blockquote>
</figure>
