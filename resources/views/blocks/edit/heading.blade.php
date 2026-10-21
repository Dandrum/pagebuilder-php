@props(['block', 'index'])

<div class="space-y-2">
    <input
        type="text"
        wire:model.blur="pageBuilderBlocks.{{ $index }}.data.text"
        placeholder="{{ __('Heading text') }}"
        class="block w-full rounded-md border-slate-300 text-lg font-semibold shadow-sm sm:text-base"
    />
    @error('pageBuilderBlocks.'.$index.'.data.text')
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror

    <div class="flex gap-3">
        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.level" class="rounded-md border-slate-300 text-sm">
            <option value="2">H2</option>
            <option value="3">H3</option>
            <option value="4">H4</option>
        </select>

        <select wire:model.live="pageBuilderBlocks.{{ $index }}.data.align" class="rounded-md border-slate-300 text-sm">
            <option value="left">{{ __('Left') }}</option>
            <option value="center">{{ __('Center') }}</option>
            <option value="right">{{ __('Right') }}</option>
        </select>
    </div>
</div>
