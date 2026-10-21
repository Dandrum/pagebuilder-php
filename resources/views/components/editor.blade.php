@props(['blocks' => [], 'blockTypes' => []])

@php
    $registry = app(\Dandrum\PageBuilder\Support\BlockRegistry::class);
@endphp

<div class="pagebuilder rounded-md border border-slate-300 bg-white p-4">
    @include('pagebuilder::components.insert-row', ['blockTypes' => $blockTypes, 'afterBlockId' => '', 'first' => true])

    <div>
        @foreach($blocks as $index => $block)
            @continue(! $registry->has($block['type']))

            @php $blockType = $registry->get($block['type']); @endphp

            <div wire:key="pagebuilder-block-{{ $block['id'] }}" class="pagebuilder-block">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ $blockType->label() }}
                    </span>

                    <div class="flex items-center gap-1 text-slate-400">
                        <button type="button" title="{{ __('Move up') }}" wire:click="movePageBuilderBlockUp('{{ $block['id'] }}')" class="cursor-pointer rounded p-1 hover:bg-slate-100 hover:text-slate-600">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .53.22l5.5 5.5a.75.75 0 1 1-1.06 1.06L10 4.81 5.03 9.78a.75.75 0 0 1-1.06-1.06l5.5-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/></svg>
                        </button>
                        <button type="button" title="{{ __('Move down') }}" wire:click="movePageBuilderBlockDown('{{ $block['id'] }}')" class="cursor-pointer rounded p-1 hover:bg-slate-100 hover:text-slate-600">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.53-.22l-5.5-5.5a.75.75 0 1 1 1.06-1.06L10 15.19l4.97-4.97a.75.75 0 1 1 1.06 1.06l-5.5 5.5A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>
                        </button>
                        <button type="button" title="{{ __('Duplicate') }}" wire:click="duplicatePageBuilderBlock('{{ $block['id'] }}')" class="cursor-pointer rounded p-1 hover:bg-slate-100 hover:text-slate-600">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 1 .439 1.061V12.5A1.5 1.5 0 0 1 14.5 14h-6A1.5 1.5 0 0 1 7 12.5v-9Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h6a1.5 1.5 0 0 0 1.5-1.5V15h-1.5a3 3 0 0 1-3-3V6h-3Z"/></svg>
                        </button>
                        <button type="button" title="{{ __('Delete') }}" wire:click="removePageBuilderBlock('{{ $block['id'] }}')" wire:confirm="{{ __('Delete this block?') }}" class="cursor-pointer rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>

                @include($blockType->editorView(), ['block' => $block, 'index' => $index])
            </div>

            @include('pagebuilder::components.insert-row', ['blockTypes' => $blockTypes, 'afterBlockId' => $block['id'], 'first' => false])
        @endforeach
    </div>
</div>
