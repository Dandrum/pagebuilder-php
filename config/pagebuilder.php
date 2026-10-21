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

];
