@props(['blockId', 'label' => ''])

{{--
    One Trix editor with its own restricted toolbar: bold, italic, headings 1-6, link, bullet/number
    list and quote. Attachments are blocked.

    Trix only knows <h1> out of the box, so ensureTrix() registers heading2..heading6 on
    Trix.config.blockAttributes (once, before the HTML is loaded). The package ships no JS build; the
    host just has to load Trix.

    The Livewire state is addressed by block id on every change instead of by index, so a
    reorder can never make a moved editor write into another block (wire:ignore keeps the
    Alpine component alive across re-renders).
--}}
@php
    $tool = 'trix-button pb-tool';
@endphp
<div
    class="pb-rich-text"
    wire:ignore
    wire:sort:ignore
    x-data="{
        id: @js($blockId),
        timer: null,
        ensureTrix() {
            const blocks = window.Trix && window.Trix.config && window.Trix.config.blockAttributes;

            if (!blocks) {
                return;
            }

            for (let level = 2; level <= 6; level++) {
                if (!blocks['heading' + level]) {
                    blocks['heading' + level] = { tagName: 'h' + level, terminal: true, breakOnReturn: true, group: false };
                }
            }
        },
        index() {
            return this.$wire.pageBuilderBlocks.findIndex((b) => b.id === this.id);
        },
        load() {
            const i = this.index();
            const trix = this.$refs.trix;

            if (i >= 0 && trix.editor) {
                trix.editor.loadHTML(this.$wire.pageBuilderBlocks[i].data.html || '');
            }
        },
        push() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                const i = this.index();

                if (i >= 0) {
                    this.$wire.$set('pageBuilderBlocks.' + i + '.data.html', this.$refs.trix.value, false);
                }
            }, 250);
        },
    }"
    x-init="ensureTrix(); $nextTick(() => { if ($refs.trix.editor) load(); })"
>
    <trix-toolbar id="pb-{{ $blockId }}-toolbar" class="pb-trix-toolbar" role="toolbar" aria-label="{{ __('Text formatting') }}">
        <div class="trix-button-row">
            <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
                <button type="button" class="{{ $tool }} pb-tool--bold" data-trix-attribute="bold" data-trix-key="b" title="{{ __('Bold') }}" aria-label="{{ __('Bold') }}" tabindex="-1">B</button>
                <button type="button" class="{{ $tool }} pb-tool--italic" data-trix-attribute="italic" data-trix-key="i" title="{{ __('Italic') }}" aria-label="{{ __('Italic') }}" tabindex="-1">I</button>
            </span>
            <span class="pb-tool-separator" aria-hidden="true"></span>
            <span class="trix-button-group trix-button-group--heading-tools" data-trix-button-group="heading-tools">
                @foreach(range(1, 6) as $level)
                    <button type="button" class="{{ $tool }} pb-tool--heading" data-trix-attribute="heading{{ $level }}" title="{{ __('Heading :n', ['n' => $level]) }}" aria-label="{{ __('Heading :n', ['n' => $level]) }}" tabindex="-1">H{{ $level }}</button>
                @endforeach
            </span>
            <span class="pb-tool-separator" aria-hidden="true"></span>
            <span class="trix-button-group trix-button-group--link-tools" data-trix-button-group="link-tools">
                <button type="button" class="{{ $tool }}" data-trix-attribute="href" data-trix-action="link" data-trix-key="k" title="{{ __('Link') }}" aria-label="{{ __('Link') }}" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
            </span>
            <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
                <button type="button" class="{{ $tool }}" data-trix-attribute="bullet" title="{{ __('Bullet list') }}" aria-label="{{ __('Bullet list') }}" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                </button>
                <button type="button" class="{{ $tool }}" data-trix-attribute="number" title="{{ __('Numbered list') }}" aria-label="{{ __('Numbered list') }}" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                </button>
                <button type="button" class="{{ $tool }}" data-trix-attribute="quote" title="{{ __('Quote') }}" aria-label="{{ __('Quote') }}" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
                </button>
            </span>
        </div>
        <div class="trix-dialogs" data-trix-dialogs>
            <div class="trix-dialog trix-dialog--link" data-trix-dialog="href" data-trix-dialog-attribute="href">
                <div class="trix-dialog__link-fields">
                    <input type="url" name="href" class="trix-input trix-input--dialog" placeholder="https://" aria-label="{{ __('Link') }}" required data-trix-input disabled>
                    <div class="trix-button-group">
                        <input type="button" class="trix-button trix-button--dialog" value="{{ __('Link') }}" data-trix-method="setAttribute">
                        <input type="button" class="trix-button trix-button--dialog" value="{{ __('Unlink') }}" data-trix-method="removeAttribute">
                    </div>
                </div>
            </div>
        </div>
    </trix-toolbar>

    <input id="pb-{{ $blockId }}-input" type="hidden">
    <trix-editor
        x-ref="trix"
        toolbar="pb-{{ $blockId }}-toolbar"
        input="pb-{{ $blockId }}-input"
        class="pb-trix-editor trix-content"
        aria-label="{{ $label }}"
        x-on:trix-initialize="load()"
        x-on:trix-change="push()"
        x-on:trix-file-accept.prevent
    ></trix-editor>
</div>
