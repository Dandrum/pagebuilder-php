@props(['data'])

@php
    $position = $data['imagePosition'] ?? 'left';
    $align = $data['align'] ?? 'left';
@endphp
<div class="pagebuilder-block pagebuilder-block--image-text flex flex-col gap-6 md:flex-row {{ $position === 'right' ? 'md:flex-row-reverse' : '' }}">
    <div class="md:w-1/2">
        <img src="{{ $data['imageUrl'] ?? '' }}" alt="" loading="lazy" class="w-full rounded-md" />
    </div>
    <div class="prose max-w-none text-{{ $align }} md:w-1/2">
        {!! $data['html'] ?? '' !!}
    </div>
</div>
