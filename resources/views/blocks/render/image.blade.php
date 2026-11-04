@props(['data', 'media' => null])

@include('pagebuilder::blocks.render.partials.image', ['data' => $data, 'media' => $media, 'urlKey' => 'url', 'figureClass' => 'pb-block pb-block--image pb-figure'])
