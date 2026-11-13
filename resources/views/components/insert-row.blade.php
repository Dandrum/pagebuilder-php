@props(['blockTypes' => [], 'afterBlockId' => '', 'first' => false, 'position' => 0])

<div class="pb-insert-row {{ $first ? 'pb-insert-row--first' : '' }}">
    <span class="pb-insert-line" aria-hidden="true"></span>
    @foreach($blockTypes as $type)
        <button
            type="button"
            wire:click="insertPageBuilderBlock('{{ $type['key'] }}', '{{ $afterBlockId }}')"
            class="pb-insert-button"
            aria-label="{{ $first ? __('Insert :type at the start', ['type' => $type['label']]) : __('Insert :type after block :n', ['type' => $type['label'], 'n' => $position]) }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            {{ $type['label'] }}
        </button>
    @endforeach
    <span class="pb-insert-line" aria-hidden="true"></span>
</div>
