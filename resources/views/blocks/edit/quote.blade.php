@props(['block', 'index', 'context' => []])

<div class="pb-block-body">
    <div class="pb-quote-edit">
        <textarea
            wire:model.blur="pageBuilderBlocks.{{ $index }}.data.text"
            rows="3"
            maxlength="{{ \Dandrum\PageBuilder\Blocks\QuoteBlock::maxLength() }}"
            placeholder="{{ __('Enter quote …') }}"
            aria-label="{{ __('Quote text') }}"
            class="pb-quote-input"
        ></textarea>
    </div>
    @error('pageBuilderBlocks.'.$index.'.data.text')
        <span class="pb-error">{{ $message }}</span>
    @enderror
</div>
