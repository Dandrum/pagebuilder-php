@props(['data'])

@php($align = config('pagebuilder.alignment', true) ? ($data['align'] ?? 'left') : null)
<div class="pb-block pb-block--paragraph pb-prose {{ $align ? 'pb-align-'.$align : '' }}">
    {!! $data['html'] ?? '' !!}
</div>
