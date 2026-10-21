@props(['data'])

@php
    $level = max(2, min(4, (int) ($data['level'] ?? 2)));
    $tag = 'h'.$level;
    $align = $data['align'] ?? 'left';
@endphp
<{{ $tag }} class="pagebuilder-block pagebuilder-block--heading text-{{ $align }}">{{ $data['text'] ?? '' }}</{{ $tag }}>
