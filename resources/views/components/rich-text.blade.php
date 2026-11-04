@props(['blockId', 'label' => ''])

{{--
    One Trix editor with its own restricted toolbar (bold, italic, link, bullet/number list).
    The Livewire state is addressed by block id on every change instead of by index, so a
    reorder can never make a moved editor write into another block (wire:ignore keeps the
    Alpine component alive across re-renders).
--}}
<div
    class="pb-rich-text"
    wire:ignore
    wire:sort:ignore
    x-data="{
        id: @js($blockId),
        timer: null,
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
    x-init="$nextTick(() => { if ($refs.trix.editor) load(); })"
>
    <trix-toolbar id="pb-{{ $blockId }}-toolbar" class="pb-trix-toolbar">
        <div class="trix-button-row">
            <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
                <button type="button" class="trix-button trix-button--icon trix-button--icon-bold" data-trix-attribute="bold" data-trix-key="b" title="{{ __('Bold') }}" aria-label="{{ __('Bold') }}" tabindex="-1">{{ __('Bold') }}</button>
                <button type="button" class="trix-button trix-button--icon trix-button--icon-italic" data-trix-attribute="italic" data-trix-key="i" title="{{ __('Italic') }}" aria-label="{{ __('Italic') }}" tabindex="-1">{{ __('Italic') }}</button>
                <button type="button" class="trix-button trix-button--icon trix-button--icon-link" data-trix-attribute="href" data-trix-action="link" data-trix-key="k" title="{{ __('Link') }}" aria-label="{{ __('Link') }}" tabindex="-1">{{ __('Link') }}</button>
            </span>
            <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
                <button type="button" class="trix-button trix-button--icon trix-button--icon-bullet-list" data-trix-attribute="bullet" title="{{ __('Bullet list') }}" aria-label="{{ __('Bullet list') }}" tabindex="-1">{{ __('Bullet list') }}</button>
                <button type="button" class="trix-button trix-button--icon trix-button--icon-number-list" data-trix-attribute="number" title="{{ __('Numbered list') }}" aria-label="{{ __('Numbered list') }}" tabindex="-1">{{ __('Numbered list') }}</button>
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
