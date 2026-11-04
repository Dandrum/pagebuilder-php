@props(['block', 'index', 'context' => []])

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
    $prefix = 'pageBuilderBlocks.'.$index.'.data.';
@endphp

<div class="pb-block-body pb-stack">
    @if($source === 'url')
        @if(filled($data['url'] ?? null))
            <img src="{{ $data['url'] }}" alt="" class="pb-preview" />
        @endif

        <div wire:loading.flex wire:target="pageBuilderUploads.{{ $id }}" class="pb-loading">
            {{ __('Uploading...') }}
        </div>

        @if($upload !== 'off')
            <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $id }}" class="pb-file" aria-label="{{ __('Image') }}" />
        @endif
        @error('pageBuilderUploads.'.$id)
            <span class="pb-error">{{ $message }}</span>
        @enderror
        @error($prefix.'url')
            <span class="pb-error">{{ $message }}</span>
        @enderror

        <input
            type="text"
            wire:model.blur="{{ $prefix }}alt"
            placeholder="{{ __('Alt text (required)') }}"
            aria-label="{{ __('Alt text') }}"
            class="pb-input"
        />
        @error($prefix.'alt')
            <span class="pb-error">{{ $message }}</span>
        @enderror

        <input
            type="text"
            wire:model.blur="{{ $prefix }}caption"
            placeholder="{{ __('Caption (optional)') }}"
            aria-label="{{ __('Caption') }}"
            class="pb-input"
        />
        @error($prefix.'caption')
            <span class="pb-error">{{ $message }}</span>
        @enderror
    @elseif($pending !== null)
        {{-- A new file is waiting for the save (deferred upload). --}}
        <div class="pb-media">
            @if(filled($pending['previewUrl'] ?? null))
                <img src="{{ $pending['previewUrl'] }}" alt="" class="pb-preview" />
            @endif
            <div class="pb-media-meta">
                <span class="pb-file-name">{{ $pending['name'] ?? '' }}</span>
                <button type="button" class="pb-button" wire:click="clearPageBuilderUpload('{{ $id }}')">{{ __('Remove file') }}</button>
            </div>
        </div>

        @foreach(['alt' => __('Alt text'), 'caption' => __('Caption'), 'credit' => __('Photographer / source')] as $field => $label)
            <label class="pb-field">
                <span class="pb-label">{{ $label }}@if(in_array($field, $required, true)) <span class="pb-required" aria-hidden="true">*</span>@endif</span>
                <input
                    type="text"
                    wire:model.blur="{{ $prefix.$field }}"
                    class="pb-input"
                    @if(in_array($field, $required, true)) required aria-required="true" @endif
                />
            </label>
            @error($prefix.$field)
                <span class="pb-error">{{ $message }}</span>
            @enderror
        @endforeach
        @error('pageBuilderUploads.'.$id)
            <span class="pb-error">{{ $message }}</span>
        @enderror
    @elseif($hasMedia)
        <div class="pb-media">
            @if(is_array($preview) && filled($preview['url'] ?? null))
                <img src="{{ $preview['url'] }}" alt="" class="pb-preview" />
            @elseif(is_array($preview))
                <span class="pb-processing">{{ __('Processing…') }}</span>
            @else
                <span class="pb-error">{{ __('The image no longer exists.') }}</span>
            @endif

            @if(is_array($preview))
                <dl class="pb-media-meta">
                    <dt>{{ __('Alt text') }}</dt>
                    <dd>{{ $preview['alt'] ?? '' }}</dd>
                    <dt>{{ __('Photographer / source') }}</dt>
                    <dd>{{ $preview['credit'] ?? '' }}</dd>
                </dl>
            @endif
        </div>

        <label class="pb-field">
            <span class="pb-label">{{ __('Caption') }}</span>
            <input
                type="text"
                wire:model.blur="{{ $prefix }}caption"
                placeholder="{{ is_array($preview) ? ($preview['caption'] ?? '') : '' }}"
                class="pb-input"
            />
        </label>
        @error($prefix.'caption')
            <span class="pb-error">{{ $message }}</span>
        @enderror

        <div class="pb-row">
            @if(! empty($options['picker']))
                <button type="button" class="pb-button" wire:click="$dispatch('pagebuilder-pick-image', { blockId: '{{ $id }}' })">{{ __('Replace') }}</button>
            @endif
            <button type="button" class="pb-button pb-button--danger" wire:click="clearPageBuilderImage('{{ $id }}')">{{ __('Remove image') }}</button>
        </div>
        @error($prefix.'media_id')
            <span class="pb-error">{{ $message }}</span>
        @enderror
    @else
        <div class="pb-row">
            @if(! empty($options['picker']))
                <button type="button" class="pb-button" wire:click="$dispatch('pagebuilder-pick-image', { blockId: '{{ $id }}' })">{{ __('Choose from library') }}</button>
            @endif

            @if($upload !== 'off')
                <div wire:loading.flex wire:target="pageBuilderUploads.{{ $id }}" class="pb-loading">
                    {{ __('Uploading...') }}
                </div>
                <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $id }}" class="pb-file" aria-label="{{ __('Upload image') }}" />
            @endif
        </div>

        @if(empty($options['picker']) && filled($options['notice'] ?? null))
            <p class="pb-notice">{{ $options['notice'] }}</p>
        @endif

        @error($prefix.'media_id')
            <span class="pb-error">{{ $message }}</span>
        @enderror
        @error('pageBuilderUploads.'.$id)
            <span class="pb-error">{{ $message }}</span>
        @enderror
    @endif
</div>
