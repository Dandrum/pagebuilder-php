<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;
use Dandrum\PageBuilder\Contracts\ImageMediaResolver;

/**
 * Image block with two possible sources (see `pagebuilder.image.source`): a plain `url`,
 * or a `media_id` resolved through the host's {@see ImageMediaResolver}.
 * The source-dependent "required" rules live in the HasPageBuilder trait, since they depend on
 * the component's options and on pending uploads.
 */
final class ImageBlock implements BlockType, ContentAwareBlock
{
    public static function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return __('Image');
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
        $normalized = [];

        $mediaId = $data['media_id'] ?? null;

        if (is_int($mediaId) || (is_string($mediaId) && trim($mediaId) !== '')) {
            $normalized['media_id'] = is_numeric($mediaId) ? (int) $mediaId : trim((string) $mediaId);
        }

        $url = is_string($data['url'] ?? null) ? trim($data['url']) : '';

        if ($url !== '' && $this->isSafeUrl($url)) {
            $normalized['url'] = $url;
        }

        if ($normalized === []) {
            return null;
        }

        foreach (['alt', 'caption', 'credit'] as $key) {
            if (is_string($data[$key] ?? null) && trim($data[$key]) !== '') {
                $normalized[$key] = trim($data[$key]);
            }
        }

        return $normalized;
    }

    public function plainText(array $data): string
    {
        // Images contribute nothing to word counts / reading time.
        return '';
    }

    private function isSafeUrl(string $url): bool
    {
        if (preg_match('~^https?://~i', $url) === 1) {
            return true;
        }

        // Relative (same-origin) paths only; "//host" and any other scheme are rejected.
        return preg_match('~^/(?!/)~', $url) === 1 || (! str_contains($url, ':') && ! str_starts_with($url, '//'));
    }
}
