@props(['block', 'index', 'context' => []])

<div class="pb-block-body">
    <div class="pb-row">
        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.level" class="pb-select" aria-label="{{ __('Heading level') }}">
            <option value="2">H2</option>
            <option value="3">H3</option>
            <option value="4">H4</option>
        </select>

        @if(! empty($context['alignment']))
            <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="pb-select" aria-label="{{ __('Alignment') }}">
                <option value="left">{{ __('Left') }}</option>
                <option value="center">{{ __('Center') }}</option>
                <option value="right">{{ __('Right') }}</option>
            </select>
        @endif
    </div>

    <input
        type="text"
        wire:model.blur="pageBuilderBlocks.{{ $index }}.data.text"
        placeholder="{{ __('Heading text') }}"
        aria-label="{{ __('Heading text') }}"
        class="pb-input pb-input--heading"
    />
    @error('pageBuilderBlocks.'.$index.'.data.text')
        <span class="pb-error">{{ $message }}</span>
    @enderror
</div>
