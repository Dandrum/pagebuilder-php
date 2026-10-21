@props(['data'])

<div class="pagebuilder-block pagebuilder-block--paragraph prose max-w-none text-{{ $data['align'] ?? 'left' }}">
    {!! $data['html'] ?? '' !!}
</div>
