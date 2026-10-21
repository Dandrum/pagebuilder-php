@props(['blocks' => [], 'blockTypes' => []])

<div
    x-data="{
        sortable: null,
        init() {
            this.sortable = new Sortable(this.$refs.pagebuilderList, {
                handle: '.pagebuilder-drag-handle',
                animation: 150,
                onEnd: () => {
                    const ids = Array.from(this.$refs.pagebuilderList.children).map(el => el.dataset.blockId);
                    $wire.reorderPageBuilderBlocks(ids);
                },
            });
        },
    }"
    class="pagebuilder space-y-4"
>
    <div x-ref="pagebuilderList" class="space-y-4">
        @foreach($blocks as $index => $block)
            @continue(! app(\Dandrum\PageBuilder\Support\BlockRegistry::class)->has($block['type']))

            <div
                wire:key="pagebuilder-block-{{ $block['id'] }}"
                data-block-id="{{ $block['id'] }}"
                class="rounded-md border border-slate-300 bg-white p-4"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="pagebuilder-drag-handle cursor-move select-none text-slate-400" title="{{ __('Drag to reorder') }}">&#x2630;</span>

                    <div class="flex gap-3 text-sm">
                        <button type="button" wire:click="duplicatePageBuilderBlock('{{ $block['id'] }}')" class="text-slate-500 hover:text-slate-700">
                            {{ __('Duplicate') }}
                        </button>
                        <button type="button" wire:click="removePageBuilderBlock('{{ $block['id'] }}')" wire:confirm="{{ __('Delete this block?') }}" class="text-red-500 hover:text-red-700">
                            {{ __('Delete') }}
                        </button>
                    </div>
                </div>

                @include(app(\Dandrum\PageBuilder\Support\BlockRegistry::class)->get($block['type'])->editorView(), ['block' => $block, 'index' => $index])
            </div>
        @endforeach
    </div>

    <div x-data="{ open: false }">
        <button
            type="button"
            x-on:click="open = ! open"
            class="cursor-pointer rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            {{ __('Add block') }}
        </button>

        {{-- Expands inline below the button instead of floating as an absolutely-positioned
             overlay, so it can never get clipped by an ancestor's `overflow:hidden` (e.g. the
             host form's rounded-corner card wrapper) — a floating/teleported menu was tried and
             reverted: teleporting it outside the Livewire component's DOM subtree broke
             `wire:click` resolution for every button inside it. --}}
        <div
            x-show="open"
            x-on:click.outside="open = false"
            x-cloak
            class="mt-2 grid grid-cols-2 gap-1 rounded-md border border-slate-300 bg-white p-2 shadow-sm sm:grid-cols-3"
        >
            @foreach($blockTypes as $type)
                <button
                    type="button"
                    wire:click="addPageBuilderBlock('{{ $type['key'] }}')"
                    x-on:click="open = false"
                    class="cursor-pointer rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                >
                    {{ $type['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</div>
