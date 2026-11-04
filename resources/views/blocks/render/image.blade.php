@props(['data', 'media' => null])

@if(filled(config('pagebuilder.image.render_view')))
    @include(config('pagebuilder.image.render_view'), ['data' => $data, 'media' => $media])
@elseif(filled($data['media_id'] ?? null))
    @php
        $resolver = app()->bound(\Dandrum\PageBuilder\Contracts\ImageMediaResolver::class)
            ? app(\Dandrum\PageBuilder\Contracts\ImageMediaResolver::class)
            : null;
        $meta = ($media !== null && $resolver !== null) ? $resolver->metadata($media) : null;
        $src = ($media !== null && $resolver !== null) ? $resolver->previewUrl($media) : null;
        $caption = filled($data['caption'] ?? null) ? $data['caption'] : ($meta['caption'] ?? '');
    @endphp
    @if($meta !== null && filled($src))
        <figure class="pb-block pb-block--image pb-figure">
            <img src="{{ $src }}" alt="{{ $meta['alt'] }}" loading="lazy" />
            @if(filled($caption) || filled($meta['credit']))
                <figcaption>
                    {{ $caption }}@if(filled($caption) && filled($meta['credit'])) · @endif @if(filled($meta['credit'])){{ __('Photo') }}: {{ $meta['credit'] }}@endif
                </figcaption>
            @endif
        </figure>
    @endif
@else
    <figure class="pb-block pb-block--image pb-figure">
        <img src="{{ $data['url'] ?? '' }}" alt="{{ $data['alt'] ?? '' }}" loading="lazy" />
        @if(filled($data['caption'] ?? null))
            <figcaption>{{ $data['caption'] }}</figcaption>
        @endif
    </figure>
@endif
