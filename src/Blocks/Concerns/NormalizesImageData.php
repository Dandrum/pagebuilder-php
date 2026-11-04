<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks\Concerns;

/**
 * Shared cleaning of the image part of a block's data (see ImageBearingBlock).
 */
trait NormalizesImageData
{
    /**
     * Whitelists the image keys. Returns an empty array when the block has no image source.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeImageData(array $data, string $urlKey): array
    {
        $normalized = [];

        $mediaId = $data['media_id'] ?? null;

        if (is_int($mediaId) || (is_string($mediaId) && trim($mediaId) !== '')) {
            $normalized['media_id'] = is_numeric($mediaId) ? (int) $mediaId : trim((string) $mediaId);
        }

        $url = is_string($data[$urlKey] ?? null) ? trim($data[$urlKey]) : '';

        if ($url !== '' && $this->isSafeImageUrl($url)) {
            $normalized[$urlKey] = $url;
        }

        if ($normalized === []) {
            return [];
        }

        foreach (['alt', 'caption', 'credit'] as $key) {
            if (is_string($data[$key] ?? null) && trim($data[$key]) !== '') {
                $normalized[$key] = trim($data[$key]);
            }
        }

        return $normalized;
    }

    private function isSafeImageUrl(string $url): bool
    {
        if (preg_match('~^https?://~i', $url) === 1) {
            return true;
        }

        // Relative (same-origin) paths only; "//host" and any other scheme are rejected.
        return preg_match('~^/(?!/)~', $url) === 1 || (! str_contains($url, ':') && ! str_starts_with($url, '//'));
    }
}
