@props(['block', 'index'])

<div class="grid gap-4 md:grid-cols-2">
    <div class="space-y-2">
        @if(filled($block['data']['imageUrl'] ?? null))
            <img src="{{ $block['data']['imageUrl'] }}" alt="" class="max-h-48 rounded-md border border-slate-200" />
        @endif

        <div wire:loading.flex wire:target="pageBuilderUploads.{{ $block['id'] }}" class="hidden items-center text-sm text-slate-500">
            {{ __('Uploading...') }}
        </div>

        <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $block['id'] }}" class="block w-full text-sm" />
        @error('pageBuilderUploads.'.$block['id'])
            <span class="text-sm text-red-600">{{ $message }}</span>
        @enderror

        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.imagePosition" class="rounded-md border-slate-300 text-sm">
            <option value="left">{{ __('Image position') }}: {{ __('Left') }}</option>
            <option value="right">{{ __('Image position') }}: {{ __('Right') }}</option>
        </select>
    </div>

    <div class="space-y-2">
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
            <input id="pagebuilder-image-text-{{ $block['id'] }}" type="hidden">
            <trix-editor
                x-ref="trix"
                input="pagebuilder-image-text-{{ $block['id'] }}"
                class="trix-content block w-full rounded-md border-slate-300 shadow-sm sm:text-sm"
            ></trix-editor>
        </div>
        @error('pageBuilderBlocks.'.$index.'.data.html')
            <span class="text-sm text-red-600">{{ $message }}</span>
        @enderror

        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="rounded-md border-slate-300 text-sm">
            <option value="left">{{ __('Left') }}</option>
            <option value="center">{{ __('Center') }}</option>
            <option value="right">{{ __('Right') }}</option>
            <option value="justify">{{ __('Justify') }}</option>
        </select>
    </div>
</div>
