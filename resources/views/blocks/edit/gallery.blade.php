@props(['block', 'index', 'context' => []])

{{-- Gallery: the slideshow switch plus one image slot per item (same image field as an image block, keyed by the item id). --}}
@php
    $blockId = $block['id'];
    $items = array_values(array_filter((array) ($block['data']['items'] ?? []), 'is_array'));
    $count = count($items);
    $max = (int) ($context['galleryMax'] ?? \Dandrum\PageBuilder\Blocks\GalleryBlock::maxImages());
    $base = 'pageBuilderBlocks.'.$index.'.data.';
@endphp

<div class="pb-block-body pb-gallery-edit">
    <label class="pb-switch">
        <input type="checkbox" wire:model.live="{{ $base }}slideshow" class="pb-switch-input" />
        <span class="pb-switch-label">{{ __('Show as slideshow') }}</span>
    </label>
    @error($base.'slideshow')
        <span class="pb-error">{{ $message }}</span>
    @enderror

    @if($count === 0)
        <p class="pb-notice">{{ __('This gallery has no images yet.') }}</p>
    @endif

    @foreach($items as $j => $item)
        @php $itemId = (string) ($item['id'] ?? ''); @endphp

        <div wire:key="pb-gallery-item-{{ $itemId }}" class="pb-gallery-item">
            <div class="pb-gallery-item-header">
                <span class="pb-gallery-item-title">{{ __('Image :n of :total', ['n' => $j + 1, 'total' => $count]) }}</span>
                <div class="pb-gallery-item-actions">
                    <button type="button" class="pb-icon-button" wire:click="movePageBuilderGalleryItem('{{ $blockId }}', '{{ $itemId }}', -1)" @disabled($j === 0) aria-label="{{ __('Move image :n up', ['n' => $j + 1]) }}" title="{{ __('Move up') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .53.22l5.5 5.5a.75.75 0 1 1-1.06 1.06L10 4.81 5.03 9.78a.75.75 0 0 1-1.06-1.06l5.5-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/></svg>
                    </button>
                    <button type="button" class="pb-icon-button" wire:click="movePageBuilderGalleryItem('{{ $blockId }}', '{{ $itemId }}', 1)" @disabled($j === $count - 1) aria-label="{{ __('Move image :n down', ['n' => $j + 1]) }}" title="{{ __('Move down') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.53-.22l-5.5-5.5a.75.75 0 1 1 1.06-1.06L10 15.19l4.97-4.97a.75.75 0 1 1 1.06 1.06l-5.5 5.5A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>
                    </button>
                    <button type="button" class="pb-icon-button pb-icon-button--danger" wire:click="removePageBuilderGalleryItem('{{ $blockId }}', '{{ $itemId }}')" wire:confirm="{{ __('Remove this image from the gallery?') }}" aria-label="{{ __('Remove image :n from the gallery', ['n' => $j + 1]) }}" title="{{ __('Delete') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>

            @include('pagebuilder::components.image-field', [
                'block' => ['id' => $itemId, 'data' => $item],
                'index' => $index,
                'context' => $context,
                'urlKey' => 'url',
                'prefix' => $base.'items.'.$j.'.',
                'clearable' => false,
                'applyAll' => $count > 1 ? ['block' => $blockId, 'item' => $itemId] : null,
            ])
        </div>
    @endforeach

    @error($base.'items')
        <span class="pb-error">{{ $message }}</span>
    @enderror
    @error('pageBuilderGalleryUploads.'.$blockId)
        <span class="pb-error">{{ $message }}</span>
    @enderror
    <div wire:loading.flex wire:target="pageBuilderGalleryUploads.{{ $blockId }}" class="pb-loading">{{ __('Uploading...') }}</div>

    <div class="pb-row pb-gallery-footer">
        <button type="button" class="pb-button" wire:click="addPageBuilderGalleryItem('{{ $blockId }}')" @disabled($count >= $max)>{{ __('Add image') }}</button>
        @if(($context['options']['upload'] ?? 'immediate') !== 'off')
            <label class="pb-button pb-file-button" @if($count >= $max) aria-disabled="true" @endif>
                {{ __('Upload several images') }}
                <input type="file" accept="image/*" multiple wire:model="pageBuilderGalleryUploads.{{ $blockId }}" x-on:livewire-upload-finish="$el.value = ''" class="pb-file" @disabled($count >= $max) />
            </label>
        @endif
        <span class="pb-gallery-count">{{ __(':count of :max images', ['count' => $count, 'max' => $max]) }}</span>
    </div>
</div>
