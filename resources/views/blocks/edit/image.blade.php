@props(['block', 'index'])

<div class="space-y-2">
    @if(filled($block['data']['url'] ?? null))
        <img src="{{ $block['data']['url'] }}" alt="" class="max-h-48 rounded-md border border-slate-200" />
    @endif

    <div wire:loading.flex wire:target="pageBuilderUploads.{{ $block['id'] }}" class="hidden items-center text-sm text-slate-500">
        {{ __('Uploading...') }}
    </div>

    <input type="file" accept="image/*" wire:model="pageBuilderUploads.{{ $block['id'] }}" class="block w-full text-sm" />
    @error('pageBuilderUploads.'.$block['id'])
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror

    <input
        type="text"
        wire:model.blur="pageBuilderBlocks.{{ $index }}.data.alt"
        placeholder="{{ __('Alt text (required)') }}"
        class="block w-full rounded-md border-slate-300 text-sm shadow-sm"
    />
    @error('pageBuilderBlocks.'.$index.'.data.alt')
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror

    <input
        type="text"
        wire:model.blur="pageBuilderBlocks.{{ $index }}.data.caption"
        placeholder="{{ __('Caption (optional)') }}"
        class="block w-full rounded-md border-slate-300 text-sm shadow-sm"
    />
</div>
