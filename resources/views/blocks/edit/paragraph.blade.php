@props(['block', 'index', 'context' => []])

<div class="pb-block-body">
    @if(! empty($context['alignment']))
        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="pb-select" aria-label="{{ __('Alignment') }}">
            <option value="left">{{ __('Left') }}</option>
            <option value="center">{{ __('Center') }}</option>
            <option value="right">{{ __('Right') }}</option>
            <option value="justify">{{ __('Justify') }}</option>
        </select>
    @endif

    @include('pagebuilder::components.rich-text', ['blockId' => $block['id'], 'label' => __('Paragraph :n', ['n' => $index + 1])])

    @error('pageBuilderBlocks.'.$index.'.data.html')
        <span class="pb-error">{{ $message }}</span>
    @enderror
</div>
