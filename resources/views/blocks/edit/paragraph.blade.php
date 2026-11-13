@props(['block', 'index', 'context' => []])

<div class="pb-block-body">
    @if(! empty($context['alignment']))
        <div class="pb-block-options">
            @include('pagebuilder::components.align-tools', ['blockId' => $block['id']])
        </div>
    @endif

    @include('pagebuilder::components.rich-text', ['blockId' => $block['id'], 'label' => __('Paragraph :n', ['n' => $index + 1]), 'alignable' => ! empty($context['alignment'])])

    @error('pageBuilderBlocks.'.$index.'.data.html')
        <span class="pb-error">{{ $message }}</span>
    @enderror
</div>
