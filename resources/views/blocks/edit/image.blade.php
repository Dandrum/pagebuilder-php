@props(['block', 'index', 'context' => []])

<div class="pb-block-body">
    @include('pagebuilder::components.image-field', ['block' => $block, 'index' => $index, 'context' => $context, 'urlKey' => 'url'])
</div>
