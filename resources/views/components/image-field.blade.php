@props(['block', 'index', 'context' => [], 'urlKey' => 'url', 'prefix' => null, 'clearable' => true, 'applyAll' => null])

{{--
    The image part of an image-bearing block (image, image + text): preview, upload / library
    picker and the alt / caption / credit row. All states of `pagebuilder.image` live here.
    For a gallery item `block` is `['id' => item id, 'data' => item]` and `prefix` points at the item
    (`pageBuilderBlocks.{i}.data.items.{j}.`); `clearable` = false hides "Remove image" (the item itself is removed instead); `applyAll` shows the "Apply to all" buttons.
--}}
@php
    $id = $block['id'];
    $data = $block['data'] ?? [];
    $options = $context['options'] ?? [];
    $source = ($options['source'] ?? 'url') === 'media' ? 'media' : 'url';
    $upload = $options['upload'] ?? 'immediate';
    $required = (array) ($options['upload_required'] ?? ['alt']);
    $pending = $context['uploads'][$id] ?? null;
    $mediaId = $data['media_id'] ?? null;
    $hasMedia = $mediaId !== null && $mediaId !== '';
    $preview = $hasMedia ? ($context['previews'][$id] ?? null) : null;
    $prefix = $prefix ?? 'pageBuilderBlocks.'.$index.'.data.';
    $picker = ! empty($options['picker']);
    $pickEvent = "\$dispatch('pagebuilder-pick-image', { blockId: '".$id."' })";
    $hasImage = $source === 'url' ? filled($data[$urlKey] ?? null) : ($pending !== null || $hasMedia);
    $imageIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
@endphp

<div class="pb-image-field">
    @if($source === 'url' && $hasImage)
        <div class="pb-preview-frame"><img src="{{ $data[$urlKey] }}" alt="" class="pb-preview" /></div>
    @elseif($source === 'media' && $pending !== null)
        {{-- A new file is waiting for the save (deferred upload). --}}
        <div class="pb-preview-frame">
            @if(filled($pending['previewUrl'] ?? null))
                <img src="{{ $pending['previewUrl'] }}" alt="" class="pb-preview" />
            @else
                <span class="pb-processing">{!! $imageIcon !!}</span>
            @endif
        </div>
        <div class="pb-row">
            <span class="pb-file-name">{{ $pending['name'] ?? '' }}</span>
            <button type="button" class="pb-button" wire:click="clearPageBuilderUpload('{{ $id }}')">{{ __('Remove file') }}</button>
        </div>
    @elseif($source === 'media' && $hasMedia)
        <div class="pb-preview-frame">
            @if(is_array($preview) && filled($preview['url'] ?? null))
                <img src="{{ $preview['url'] }}" alt="" class="pb-preview" />
            @elseif(is_array($preview))
                <span class="pb-processing">{!! $imageIcon !!}{{ __('Processing…') }}</span>
            @else
                <span class="pb-error">{{ __('The image no longer exists.') }}</span>
            @endif
        </div>
        <div class="pb-row">
            @if($picker)
                <button type="button" class="pb-button" wire:click="{{ $pickEvent }}">{{ __('Replace') }}</button>
            @endif
            @if($clearable)
                <button type="button" class="pb-button pb-button--danger" wire:click="clearPageBuilderImage('{{ $id }}')">{{ __('Remove image') }}</button>
            @endif
        </div>
    @endif

    @if(! $hasImage)
        <div class="pb-dropzone">
            <span class="pb-dropzone-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
            </span>
            @if($picker || $upload !== 'off')
                <span class="pb-dropzone-title">
                    @if($picker && $upload !== 'off')
                        {{ __('Upload an image or choose one from the library') }}
                    @elseif($picker)
                        {{ __('Choose from library') }}
                    @else
                        {{ __('Upload image') }}
                    @endif
                </span>
                <span class="pb-dropzone-hint">{{ __('JPG, PNG · landscape recommended') }}</span>
            @endif
            <div class="pb-dropzone-actions">
                @if($picker)
                    <button type="button" class="pb-button" wire:click="{{ $pickEvent }}">{{ __('Choose from library') }}</button>
                @endif
                @if($upload !== 'off')
                    <label class="pb-button pb-file-button">
                        {{ __('Upload image') }}
                        <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $id }}" class="pb-file" />
                    </label>
                @endif
            </div>
            @if(! $picker && filled($options['notice'] ?? null))
                <p class="pb-notice">{{ $options['notice'] }}</p>
            @endif
        </div>
    @elseif($source === 'url' && $upload !== 'off')
        <label class="pb-button pb-file-button">
            {{ __('Upload image') }}
            <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $id }}" class="pb-file" />
        </label>
    @endif

    <div wire:loading.flex wire:target="pageBuilderUploads.{{ $id }}" class="pb-loading">
        {{ __('Uploading...') }}
    </div>

    @error('pageBuilderUploads.'.$id)
        <span class="pb-error">{{ $message }}</span>
    @enderror
    @error($prefix.'media_id')
        <span class="pb-error">{{ $message }}</span>
    @enderror
    @error($prefix.$urlKey)
        <span class="pb-error">{{ $message }}</span>
    @enderror

    @if($source === 'url')
        <x-pagebuilder::image-meta :prefix="$prefix" :fields="['alt', 'caption']" :required="['alt']" :apply-all="$applyAll" />
    @elseif($pending !== null)
        <x-pagebuilder::image-meta :prefix="$prefix" :required="$required" :apply-all="$applyAll" />
    @elseif($hasMedia)
        <x-pagebuilder::image-meta
            :prefix="$prefix"
            :apply-all="$applyAll"
            :applyable="['caption']"
            :readonly="['alt' => is_array($preview) ? ($preview['alt'] ?? '') : '', 'credit' => is_array($preview) ? ($preview['credit'] ?? '') : '']"
            :placeholders="['caption' => is_array($preview) ? ($preview['caption'] ?? '') : '']"
        />
    @endif
</div>
