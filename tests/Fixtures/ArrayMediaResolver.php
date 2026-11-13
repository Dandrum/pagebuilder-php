<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Fixtures;

use Dandrum\PageBuilder\Contracts\ImageMediaResolver;

final class ArrayMediaResolver implements ImageMediaResolver
{
    public static int $calls = 0;

    /** @var array<int, array{alt: string, caption: string, credit: string, url: string|null}> */
    public static array $media = [];

    public static function reset(): void
    {
        self::$calls = 0;
        self::$media = [
            1 => ['alt' => 'Rathaus', 'caption' => 'Das Rathaus', 'credit' => 'Max Muster', 'url' => '/m/1.jpg'],
            2 => ['alt' => 'Brücke', 'caption' => '', 'credit' => 'Eva Beispiel', 'url' => null],
        ];
    }

    public function findMany(array $ids): array
    {
        self::$calls++;

        return array_intersect_key(self::$media, array_flip(array_map('intval', $ids)));
    }

    public function previewUrl(mixed $media): ?string
    {
        return $media['url'];
    }

    public function metadata(mixed $media): array
    {
        return ['alt' => $media['alt'], 'caption' => $media['caption'], 'credit' => $media['credit']];
    }
}
