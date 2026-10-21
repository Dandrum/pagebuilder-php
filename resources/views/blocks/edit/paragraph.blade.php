@props(['block', 'index'])

<div class="space-y-2">
    <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="rounded-md border-slate-300 text-sm">
        <option value="left">{{ __('Left') }}</option>
        <option value="center">{{ __('Center') }}</option>
        <option value="right">{{ __('Right') }}</option>
        <option value="justify">{{ __('Justify') }}</option>
    </select>

    <div
        x-data="{
            content: @entangle('pageBuilderBlocks.'.$index.'.data.html'),
            initTrix() {
                const trix = this.$refs.trix;
                trix.addEventListener('trix-initialize', () => {
                    if (this.content) trix.editor.loadHTML(this.content);
                });
                trix.addEventListener('trix-change', () => {
                    this.content = trix.innerHTML;
                });
            },
        }"
        x-init="initTrix()"
        wire:ignore
    >
        <input id="pagebuilder-paragraph-{{ $block['id'] }}" type="hidden">
        <trix-editor
            x-ref="trix"
            input="pagebuilder-paragraph-{{ $block['id'] }}"
            class="trix-content block w-full rounded-md border-slate-300 shadow-sm sm:text-sm"
        ></trix-editor>
    </div>
    @error('pageBuilderBlocks.'.$index.'.data.html')
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror
</div>
