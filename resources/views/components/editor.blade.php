@props(['blocks' => [], 'blockTypes' => [], 'context' => []])

@php
    $registry = app(\Dandrum\PageBuilder\Support\BlockRegistry::class);
    $context = array_replace([
        'options' => (array) config('pagebuilder.image', []),
        'previews' => [],
        'uploads' => [],
        'alignment' => (bool) config('pagebuilder.alignment', true),
    ], (array) $context);
    $lastIndex = count($blocks) - 1;
@endphp

<div class="pagebuilder">
    @include('pagebuilder::components.insert-row', ['blockTypes' => $blockTypes, 'afterBlockId' => '', 'first' => true, 'position' => 0])

    <div class="pb-list" wire:sort="sortPageBuilderBlock">
        @foreach($blocks as $index => $block)
            @continue(! $registry->has($block['type']))

            @php
                $blockType = $registry->get($block['type']);
                $blockId = $block['id'];
                $n = $index + 1;
                $name = $blockType->label();
            @endphp

            <div wire:key="pagebuilder-block-{{ $blockId }}" wire:sort:item="{{ $blockId }}" class="pb-item">
                <div class="pb-block-frame">
                    <div class="pb-block-header">
                        <div class="pb-block-title">
                            <button type="button" wire:sort:handle class="pb-icon-button pb-handle" aria-label="{{ __('Drag :type :n to reorder', ['type' => $name, 'n' => $n]) }}" title="{{ __('Drag to reorder') }}">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 4a1.25 1.25 0 1 1-2.5 0A1.25 1.25 0 0 1 7 4Zm0 6a1.25 1.25 0 1 1-2.5 0A1.25 1.25 0 0 1 7 10Zm-1.25 7.25a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5ZM15.5 4a1.25 1.25 0 1 1-2.5 0 1.25 1.25 0 0 1 2.5 0ZM14.25 11.25a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5ZM15.5 16a1.25 1.25 0 1 1-2.5 0 1.25 1.25 0 0 1 2.5 0Z"/></svg>
                            </button>
                            <span class="pb-block-label">{{ $name }}</span>
                        </div>

                        <div class="pb-block-actions">
                            <button type="button" class="pb-icon-button" wire:click="movePageBuilderBlockUp('{{ $blockId }}')" @disabled($index === 0) aria-label="{{ __('Move :type :n up', ['type' => $name, 'n' => $n]) }}" title="{{ __('Move up') }}">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .53.22l5.5 5.5a.75.75 0 1 1-1.06 1.06L10 4.81 5.03 9.78a.75.75 0 0 1-1.06-1.06l5.5-5.5A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/></svg>
                            </button>
                            <button type="button" class="pb-icon-button" wire:click="movePageBuilderBlockDown('{{ $blockId }}')" @disabled($index === $lastIndex) aria-label="{{ __('Move :type :n down', ['type' => $name, 'n' => $n]) }}" title="{{ __('Move down') }}">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.53-.22l-5.5-5.5a.75.75 0 1 1 1.06-1.06L10 15.19l4.97-4.97a.75.75 0 1 1 1.06 1.06l-5.5 5.5A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/></svg>
                            </button>
                            <button type="button" class="pb-icon-button" wire:click="duplicatePageBuilderBlock('{{ $blockId }}')" aria-label="{{ __('Duplicate :type :n', ['type' => $name, 'n' => $n]) }}" title="{{ __('Duplicate') }}">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 1 .439 1.061V12.5A1.5 1.5 0 0 1 14.5 14h-6A1.5 1.5 0 0 1 7 12.5v-9Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h6a1.5 1.5 0 0 0 1.5-1.5V15h-1.5a3 3 0 0 1-3-3V6h-3Z"/></svg>
                            </button>
                            <button type="button" class="pb-icon-button pb-icon-button--danger" wire:click="removePageBuilderBlock('{{ $blockId }}')" wire:confirm="{{ __('Delete this block?') }}" aria-label="{{ __('Delete :type :n', ['type' => $name, 'n' => $n]) }}" title="{{ __('Delete') }}">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </div>

                    @include($blockType->editorView(), ['block' => $block, 'index' => $index, 'context' => $context])
                </div>

                @include('pagebuilder::components.insert-row', ['blockTypes' => $blockTypes, 'afterBlockId' => $blockId, 'first' => false, 'position' => $n])
            </div>
        @endforeach
    </div>
</div>
