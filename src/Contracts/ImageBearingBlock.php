<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Contracts;

/**
 * Marker for block types that carry an image in the shared image shape (`media_id` or a URL key,
 * plus `alt`, `caption`, `credit`). The HasPageBuilder trait gives such blocks the picker, upload,
 * clear and image validation behaviour configured in `pagebuilder.image`.
 */
interface ImageBearingBlock
{
    /**
     * Data key that holds the image URL when `pagebuilder.image.source` is `url`
     * (`url` for the image block, `imageUrl` for image + text).
     */
    public function imageUrlKey(): string;
}
