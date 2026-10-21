@props(['data'])

<figure class="pagebuilder-block pagebuilder-block--image">
    <img src="{{ $data['url'] ?? '' }}" alt="{{ $data['alt'] ?? '' }}" loading="lazy" class="w-full rounded-md" />
    @if(filled($data['caption'] ?? null))
        <figcaption class="mt-2 text-sm text-slate-500">{{ $data['caption'] }}</figcaption>
    @endif
</figure>
