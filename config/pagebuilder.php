<?php

declare(strict_types=1);

use Dandrum\PageBuilder\Blocks\GalleryBlock;
use Dandrum\PageBuilder\Blocks\HeadingBlock;
use Dandrum\PageBuilder\Blocks\ImageBlock;
use Dandrum\PageBuilder\Blocks\ImageTextBlock;
use Dandrum\PageBuilder\Blocks\ParagraphBlock;
use Dandrum\PageBuilder\Blocks\QuoteBlock;

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
        QuoteBlock::class,
        GalleryBlock::class,
    ],

    /*
     * Upper bounds enforced by the validation rules the trait builds.
     */
    'max_blocks' => 200,
    'max_html_length' => 20000,
    // Zitat-Block (plain text): validation cap and storage truncation.
    'max_quote_length' => 500,

    /*
     * false: the editor shows no alignment selects and the renderer ignores `align`.
     */
    'alignment' => true,

    /*
     * Server-side HTML whitelist applied to paragraph / image+text HTML whenever a
     * document is normalized (see PageBuilderDocument::normalized()).
     */
    'sanitizer' => [
        // HTMLPurifier "HTML.Allowed" syntax. Trix emits <div>, <h1>-<h6> (see rich-text.blade.php) and <blockquote>; migrated content may use <p>.
        'allowed' => 'p,div,br,strong,em,a[href],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote',
        'schemes' => ['http', 'https', 'mailto'],
        'nofollow' => true,
        // null => storage_path('framework/cache/pagebuilder-purifier'); false => no definition cache.
        'cache_path' => null,
    ],

    /*
     * Image behaviour, shared by the image and the image + text block.
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
        // Blade view used by <x-pagebuilder::render> for image blocks and for the image side of image + text blocks (receives `data`, `block`, `media`).
        'render_view' => null,
        // Text shown when neither the picker nor an upload is available.
        'notice' => null,
    ],

    /*
     * Gallery block (its images follow the `image` options above: source, upload, picker, upload_required).
     */
    'gallery' => [
        'max_images' => 30,
        // Blade view used by <x-pagebuilder::render> instead of the plain list; receives `images`
        // (resolved: id, media, url, alt, caption, credit), `slideshow`, `block` and `data`.
        'render_view' => null,
    ],

];
