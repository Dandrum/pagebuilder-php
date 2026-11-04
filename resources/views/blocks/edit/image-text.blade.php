@props(['block', 'index', 'context' => []])

<div class="pb-block-body pb-columns">
    <div class="pb-stack">
        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.imagePosition" class="pb-select" aria-label="{{ __('Image position') }}">
            <option value="left">{{ __('Image position') }}: {{ __('Left') }}</option>
            <option value="right">{{ __('Image position') }}: {{ __('Right') }}</option>
        </select>

        @if(filled($block['data']['imageUrl'] ?? null))
            <img src="{{ $block['data']['imageUrl'] }}" alt="" class="pb-preview" />
        @endif

        <div wire:loading.flex wire:target="pageBuilderUploads.{{ $block['id'] }}" class="pb-loading">
            {{ __('Uploading...') }}
        </div>

        <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $block['id'] }}" class="pb-file" aria-label="{{ __('Image') }}" />
        @error('pageBuilderUploads.'.$block['id'])
            <span class="pb-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="pb-stack">
        @if(! empty($context['alignment']))
            <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="pb-select" aria-label="{{ __('Alignment') }}">
                <option value="left">{{ __('Left') }}</option>
                <option value="center">{{ __('Center') }}</option>
                <option value="right">{{ __('Right') }}</option>
                <option value="justify">{{ __('Justify') }}</option>
            </select>
        @endif

        @include('pagebuilder::components.rich-text', ['blockId' => $block['id'], 'label' => __('Image + Text :n', ['n' => $index + 1])])

        @error('pageBuilderBlocks.'.$index.'.data.html')
            <span class="pb-error">{{ $message }}</span>
        @enderror
    </div>
</div>
