@foreach($blocks as $block)
    @continue(! $registry->has($block['type']))

    @include($registry->get($block['type'])->renderView(), ['data' => $block['data'] ?? [], 'block' => $block])
@endforeach
