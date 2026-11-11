@props(['blockId'])

{{--
    Left / center / right / justify buttons for one block's data.align. The value is written by block id
    ($wire.$set(..., false), no request) and read back reactively, so the buttons and the Trix area
    (rich-text with `alignable`) follow it without an index-bound entangle.
--}}
@php
    $icons = [
        'left' => '<line x1="21" x2="3" y1="6" y2="6"/><line x1="15" x2="3" y1="12" y2="12"/><line x1="17" x2="3" y1="18" y2="18"/>',
        'center' => '<line x1="21" x2="3" y1="6" y2="6"/><line x1="17" x2="7" y1="12" y2="12"/><line x1="19" x2="5" y1="18" y2="18"/>',
        'right' => '<line x1="21" x2="3" y1="6" y2="6"/><line x1="21" x2="9" y1="12" y2="12"/><line x1="21" x2="7" y1="18" y2="18"/>',
        'justify' => '<line x1="21" x2="3" y1="6" y2="6"/><line x1="21" x2="3" y1="12" y2="12"/><line x1="21" x2="3" y1="18" y2="18"/>',
    ];
    $names = ['left' => __('Align left'), 'center' => __('Align center'), 'right' => __('Align right'), 'justify' => __('Justify')];
@endphp
<div
    class="pb-segmented pb-align-tools"
    role="group"
    aria-label="{{ __('Alignment') }}"
    wire:ignore
    x-data="{
        id: @js($blockId),
        current() {
            const block = this.$wire.pageBuilderBlocks.find((b) => b.id === this.id);
            const value = block && block.data ? block.data.align : 'left';

            return ['left', 'center', 'right', 'justify'].includes(value) ? value : 'left';
        },
        set(value) {
            const i = this.$wire.pageBuilderBlocks.findIndex((b) => b.id === this.id);

            if (i >= 0) {
                this.$wire.$set('pageBuilderBlocks.' + i + '.data.align', value, false);
            }
        },
    }"
>
    @foreach($icons as $value => $paths)
        <button
            type="button"
            class="pb-align-button"
            title="{{ $names[$value] }}"
            aria-label="{{ $names[$value] }}"
            x-on:click="set(@js($value))"
            x-bind:class="{ 'pb-align-button--active': current() === @js($value) }"
            x-bind:aria-pressed="(current() === @js($value)).toString()"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
        </button>
    @endforeach
</div>
