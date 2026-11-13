@props(['data', 'block' => [], 'mediaMap' => []])

{{-- Gallery. A configured `pagebuilder.gallery.render_view` takes over completely; the fallback is a JS-free list of figures. --}}
@php
    $images = (new \Dandrum\PageBuilder\Blocks\GalleryBlock)->images($data, (array) $mediaMap);
    $slideshow = filter_var($data['slideshow'] ?? false, FILTER_VALIDATE_BOOL);
@endphp

@if($images !== [])
    @if(filled(config('pagebuilder.gallery.render_view')))
        @include(config('pagebuilder.gallery.render_view'), ['images' => $images, 'slideshow' => $slideshow, 'block' => $block, 'data' => $data])
    @else
        <div class="pb-block pb-block--gallery">
            <ul class="pb-gallery">
                @foreach($images as $image)
                    @continue(blank($image['url']))
                    <li class="pb-gallery-item-view"><figure>
                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" />
                        @if(filled($image['caption']) || filled($image['credit']))
                            <figcaption>
                                {{ $image['caption'] }}@if(filled($image['caption']) && filled($image['credit'])) · @endif @if(filled($image['credit'])){{ __('Photo') }}: {{ $image['credit'] }}@endif
                            </figcaption>
                        @endif
                        </figure></li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
