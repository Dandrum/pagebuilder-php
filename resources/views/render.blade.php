@foreach($blocks as $block)
    @continue(! is_array($block) || ! is_string($block['type'] ?? null) || ! $registry->has($block['type']))

    @php
        $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
        $blockMediaId = $blockData['media_id'] ?? null;
    @endphp

    @include($registry->get($block['type'])->renderView(), [
        'data' => $blockData,
        'block' => $block,
        'media' => ($blockMediaId !== null && $blockMediaId !== '') ? ($media[$blockMediaId] ?? null) : null,
        'mediaMap' => $media,
    ])
@endforeach
