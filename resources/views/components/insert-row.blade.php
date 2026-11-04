@props(['blockTypes' => [], 'afterBlockId' => '', 'first' => false, 'position' => 0])

<div class="pb-insert-row {{ $first ? 'pb-insert-row--first' : '' }}">
    @foreach($blockTypes as $type)
        <button
            type="button"
            wire:click="insertPageBuilderBlock('{{ $type['key'] }}', '{{ $afterBlockId }}')"
            class="pb-insert-button"
            aria-label="{{ $first ? __('Insert :type at the start', ['type' => $type['label']]) : __('Insert :type after block :n', ['type' => $type['label'], 'n' => $position]) }}"
        >+ {{ $type['label'] }}</button>
    @endforeach
</div>
