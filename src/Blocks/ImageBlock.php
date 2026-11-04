<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Blocks\Concerns\NormalizesImageData;
use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;
use Dandrum\PageBuilder\Contracts\ImageBearingBlock;
use Dandrum\PageBuilder\Contracts\ImageMediaResolver;

/**
 * Image block with two possible sources (see `pagebuilder.image.source`): a plain `url`,
 * or a `media_id` resolved through the host's {@see ImageMediaResolver}.
 * The source-dependent "required" rules live in the HasPageBuilder trait, since they depend on
 * the component's options and on pending uploads.
 */
final class ImageBlock implements BlockType, ContentAwareBlock, ImageBearingBlock
{
    use NormalizesImageData;

    public static function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return __('Image');
    }

    public function imageUrlKey(): string
    {
        return 'url';
    }

    public function defaultData(): array
    {
        return [
            'media_id' => null,
            'url' => '',
            'alt' => '',
            'caption' => '',
            'credit' => '',
        ];
    }

    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function editorView(): string
    {
        return 'pagebuilder::blocks.edit.image';
    }

    public function renderView(): string
    {
        return 'pagebuilder::blocks.render.image';
    }

    public function normalize(array $data): ?array
    {
        $normalized = $this->normalizeImageData($data, 'url');

        return $normalized === [] ? null : $normalized;
    }

    public function plainText(array $data): string
    {
        // Images contribute nothing to word counts / reading time.
        return '';
    }
}
