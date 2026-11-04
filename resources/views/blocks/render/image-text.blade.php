@props(['data'])

@php
    $position = $data['imagePosition'] ?? 'left';
    $align = config('pagebuilder.alignment', true) ? ($data['align'] ?? 'left') : null;
@endphp
<div class="pb-block pb-block--image-text {{ $position === 'right' ? 'pb-block--image-right' : '' }}">
    <div class="pb-image-text-media">
        <img src="{{ $data['imageUrl'] ?? '' }}" alt="" loading="lazy" />
    </div>
    <div class="pb-prose {{ $align ? 'pb-align-'.$align : '' }}">
        {!! $data['html'] ?? '' !!}
    </div>
</div>
