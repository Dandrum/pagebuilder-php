@props(['data', 'media' => null])

{{-- Image beside text; stacks on narrow screens. The image side goes through the same `pagebuilder.image.render_view` hook as the image block. --}}
@php
    $position = $data['imagePosition'] ?? 'left';
    $align = config('pagebuilder.alignment', true) ? ($data['align'] ?? 'left') : null;
@endphp
<div class="pb-block pb-block--image-text {{ $position === 'right' ? 'pb-block--image-right' : '' }}">
    <div class="pb-image-text-media">
        @include('pagebuilder::blocks.render.partials.image', ['data' => $data, 'media' => $media, 'urlKey' => 'imageUrl', 'figureClass' => 'pb-figure'])
    </div>
    <div class="pb-image-text-body pb-prose {{ $align ? 'pb-align-'.$align : '' }}">
        {!! $data['html'] ?? '' !!}
    </div>
</div>
