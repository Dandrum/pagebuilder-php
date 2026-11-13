<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Contracts;

/**
 * Bridges image blocks (`data.media_id`) to the host app's media library.
 */
interface ImageMediaResolver
{
    /**
     * @param  list<int|string>  $ids
     * @return array<int|string, mixed> host media objects keyed by id (missing ids are simply absent)
     */
    public function findMany(array $ids): array;

    /**
     * Thumbnail URL for the editor, or null while the media is still being processed.
     */
    public function previewUrl(mixed $media): ?string;

    /**
     * @return array{alt: string, caption: string, credit: string}
     */
    public function metadata(mixed $media): array;
}
