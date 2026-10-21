@props(['blockTypes' => [], 'afterBlockId' => '', 'first' => false])

<div class="flex flex-wrap items-center justify-center gap-2 py-3 {{ $first ? '' : 'mt-1 border-t border-slate-200' }}">
    @foreach($blockTypes as $type)
        <button
            type="button"
            wire:click="insertPageBuilderBlock('{{ $type['key'] }}', '{{ $afterBlockId }}')"
            class="cursor-pointer rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-600 hover:border-slate-400 hover:bg-slate-50"
        >+ {{ $type['label'] }}</button>
    @endforeach
</div>
