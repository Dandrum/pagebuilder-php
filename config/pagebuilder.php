<?php

declare(strict_types=1);

use Dandrum\PageBuilder\Blocks\HeadingBlock;
use Dandrum\PageBuilder\Blocks\ImageBlock;
use Dandrum\PageBuilder\Blocks\ImageTextBlock;
use Dandrum\PageBuilder\Blocks\ParagraphBlock;

return [

    /*
     * Block type classes available in the editor's "add block" menu and the
     * renderer. Each must implement \Dandrum\PageBuilder\Contracts\BlockType.
     * Append your own app-level block types here (after publishing this
     * config) instead of forking the package.
     */
    'blocks' => [
        ParagraphBlock::class,
        HeadingBlock::class,
        ImageBlock::class,
        ImageTextBlock::class,
    ],

    /*
     * Upper bounds enforced by the validation rules the trait builds.
     */
    'max_blocks' => 200,
    'max_html_length' => 20000,

    /*
     * false: the editor shows no alignment selects and the renderer ignores `align`.
     */
    'alignment' => true,

    /*
     * Server-side HTML whitelist applied to paragraph / image+text HTML whenever a
     * document is normalized (see PageBuilderDocument::normalized()).
     */
    'sanitizer' => [
        // HTMLPurifier "HTML.Allowed" syntax. Trix emits <div>, migrated content may use <p>.
        'allowed' => 'p,div,br,strong,em,a[href],ul,ol,li',
        'schemes' => ['http', 'https', 'mailto'],
        'nofollow' => true,
        // null => storage_path('framework/cache/pagebuilder-purifier'); false => no definition cache.
        'cache_path' => null,
    ],

    /*
     * Image block behaviour.
     */
    'image' => [
        // 'url': the block stores `data.url`. 'media': it stores `data.media_id`, resolved via `resolver`.
        'source' => 'url',
        // class-string<\Dandrum\PageBuilder\Contracts\ImageMediaResolver>, required for source 'media'.
        'resolver' => null,
        // 'immediate': store on selection. 'deferred': keep the file until the host calls
        // storePendingPageBuilderUploads(). 'off': no upload input.
        'upload' => 'immediate',
        // Show a "Choose from library" button (dispatches the `pagebuilder-pick-image` event).
        'picker' => false,
        // Subset of alt|caption|credit that is required together with a new upload.
        'upload_required' => ['alt'],
        // Blade view used by <x-pagebuilder::render> for image blocks (receives `data`, `block`, `media`).
        'render_view' => null,
        // Text shown when neither the picker nor an upload is available.
        'notice' => null,
    ],

];
