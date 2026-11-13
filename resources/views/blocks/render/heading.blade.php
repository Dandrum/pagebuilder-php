@props(['data'])

@php
    $level = max(2, min(4, (int) ($data['level'] ?? 2)));
    $tag = 'h'.$level;
    $align = config('pagebuilder.alignment', true) ? ($data['align'] ?? 'left') : null;
@endphp
<{{ $tag }} class="pb-block pb-block--heading {{ $align ? 'pb-align-'.$align : '' }}">{{ $data['text'] ?? '' }}</{{ $tag }}>
