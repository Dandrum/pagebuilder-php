@props(['block', 'index', 'context' => []])

@php
    $position = ($block['data']['imagePosition'] ?? 'left') === 'right' ? 'right' : 'left';
    $positionName = 'pb-position-'.$block['id'];
@endphp

<div class="pb-block-body">
    <div class="pb-block-options">
        <fieldset class="pb-segmented">
            <legend class="pb-visually-hidden">{{ __('Image position') }}</legend>
            @foreach(['left' => __('Image left'), 'right' => __('Image right')] as $value => $text)
                <label class="pb-segmented-option">
                    <input type="radio" name="{{ $positionName }}" value="{{ $value }}" wire:model.live="pageBuilderBlocks.{{ $index }}.data.imagePosition" class="pb-segmented-input" />
                    <span>{{ $text }}</span>
                </label>
            @endforeach
        </fieldset>

        @if(! empty($context['alignment']))
            <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="pb-select" aria-label="{{ __('Alignment') }}">
                <option value="left">{{ __('Left') }}</option>
                <option value="center">{{ __('Center') }}</option>
                <option value="right">{{ __('Right') }}</option>
                <option value="justify">{{ __('Justify') }}</option>
            </select>
        @endif
    </div>

    @error('pageBuilderBlocks.'.$index.'.data.imagePosition')
        <span class="pb-error">{{ $message }}</span>
    @enderror

    <div class="pb-image-text-edit {{ $position === 'right' ? 'pb-image-text-edit--right' : '' }}">
        <div class="pb-image-text-edit-image">
            @include('pagebuilder::components.image-field', ['block' => $block, 'index' => $index, 'context' => $context, 'urlKey' => 'imageUrl'])
        </div>

        <div class="pb-image-text-edit-text">
            @include('pagebuilder::components.rich-text', ['blockId' => $block['id'], 'label' => __('Image + Text :n', ['n' => $index + 1])])

            @error('pageBuilderBlocks.'.$index.'.data.html')
                <span class="pb-error">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
