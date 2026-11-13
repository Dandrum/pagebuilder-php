<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Blocks\Concerns\NormalizesImageData;
use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;
use Dandrum\PageBuilder\Contracts\ImageMediaResolver;
use Illuminate\Support\Str;

/**
 * A gallery: an ordered list of images (`data.items[]`, each with its own id, `media_id` or `url`,
 * optional `caption`) plus the `slideshow` switch. Deliberately not an ImageBearingBlock: the
 * editor trait addresses each item as an image "slot" by the item's id instead.
 */
final class GalleryBlock implements BlockType, ContentAwareBlock
{
    use NormalizesImageData;

    public static function key(): string
    {
        return 'gallery';
    }

    public static function maxImages(): int
    {
        return max(1, (int) config('pagebuilder.gallery.max_images', 30));
    }

    public function label(): string
    {
        return __('Gallery');
    }

    public function defaultData(): array
    {
        return ['slideshow' => false, 'items' => []];
    }

    /**
     * A new, empty image slot as the editor keeps it in its state.
     *
     * @return array<string, mixed>
     */
    public function emptyItem(): array
    {
        return [
            'id' => (string) Str::uuid(),
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
            'slideshow' => ['boolean'],
            'items' => ['array', 'max:'.self::maxImages()],
        ];
    }

    public function editorView(): string
    {
        return 'pagebuilder::blocks.edit.gallery';
    }

    public function renderView(): string
    {
        return 'pagebuilder::blocks.render.gallery';
    }

    public function normalize(array $data): ?array
    {
        $items = [];
        $seen = [];
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $image = $this->normalizeImageData($item, 'url');

            if ($image === []) {
                continue;
            }

            $id = is_string($item['id'] ?? null) ? trim($item['id']) : '';

            if ($id === '' || isset($seen[$id])) {
                $id = (string) Str::uuid();
            }

            $seen[$id] = true;
            $items[] = ['id' => $id] + $image;

            if (count($items) >= self::maxImages()) {
                break;
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'slideshow' => filter_var($data['slideshow'] ?? false, FILTER_VALIDATE_BOOL),
            'items' => $items,
        ];
    }

    public function plainText(array $data): string
    {
        return '';
    }

    /**
     * Resolves the items for rendering: skips items whose media is gone (or has no preview URL).
     * `caption` is the per-usage override, else the media's own caption; `media` is the host object
     * (null for plain-URL items).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int|string, mixed>  $mediaMap  host media keyed by id (see ImageMediaResolver::findMany())
     * @return list<array{id: string, media: mixed, url: string|null, alt: string, caption: string, credit: string}>
     */
    public function images(array $data, array $mediaMap): array
    {
        $resolver = app()->bound(ImageMediaResolver::class) ? app(ImageMediaResolver::class) : null;
        $images = [];

        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $override = is_string($item['caption'] ?? null) ? trim($item['caption']) : '';

            if (filled($item['media_id'] ?? null)) {
                $media = $mediaMap[$item['media_id']] ?? null;

                if ($media === null || $resolver === null) {
                    continue;
                }

                $meta = $resolver->metadata($media);
                $images[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'media' => $media,
                    'url' => $resolver->previewUrl($media),
                    'alt' => (string) ($meta['alt'] ?? ''),
                    'caption' => $override !== '' ? $override : (string) ($meta['caption'] ?? ''),
                    'credit' => (string) ($meta['credit'] ?? ''),
                ];
            } elseif (filled($item['url'] ?? null)) {
                $images[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'media' => null,
                    'url' => (string) $item['url'],
                    'alt' => (string) ($item['alt'] ?? ''),
                    'caption' => $override,
                    'credit' => '',
                ];
            }
        }

        return $images;
    }
}
