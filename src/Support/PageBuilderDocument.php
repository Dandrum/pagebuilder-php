<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Support;

use Dandrum\PageBuilder\Contracts\ContentAwareBlock;

final class PageBuilderDocument
{
    /**
     * @param  array<int, array{id: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function __construct(
        public readonly int $version,
        public readonly array $blocks,
    ) {}

    public static function fromJson(?string $json): self
    {
        if (blank($json)) {
            return new self(1, []);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true);

        return self::fromArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $blocks = $data['blocks'] ?? [];

        return new self(
            version: (int) ($data['version'] ?? 1),
            blocks: is_array($blocks) ? $blocks : [],
        );
    }

    /**
     * Drops malformed, unregistered and duplicate-id blocks, lets every block clean its own data
     * (whitelisting keys, sanitizing HTML, dropping empty ones) and re-indexes. Idempotent.
     */
    public function normalized(): self
    {
        $registry = app(BlockRegistry::class);
        $seen = [];
        $blocks = [];

        foreach ($this->blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $id = $block['id'] ?? null;
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            if (! is_string($id) || $id === '' || ! is_string($type) || ! is_array($data)) {
                continue;
            }

            if (isset($seen[$id]) || ! $registry->has($type)) {
                continue;
            }

            $blockType = $registry->get($type);

            if ($blockType instanceof ContentAwareBlock) {
                $data = $blockType->normalize($data);

                if ($data === null) {
                    continue;
                }
            }

            $seen[$id] = true;
            $blocks[] = ['id' => $id, 'type' => $type, 'data' => $data];
        }

        return new self($this->version, $blocks);
    }

    /**
     * Distinct, non-empty `data.media_id` values of all blocks, including the `data.items[].media_id`
     * of gallery blocks, in document order.
     *
     * @return list<int|string>
     */
    public function mediaIds(): array
    {
        $ids = [];

        foreach ($this->blocks as $block) {
            if (! is_array($block) || ! is_array($block['data'] ?? null)) {
                continue;
            }

            $candidates = [$block['data']['media_id'] ?? null];

            foreach (is_array($block['data']['items'] ?? null) ? $block['data']['items'] : [] as $item) {
                $candidates[] = is_array($item) ? ($item['media_id'] ?? null) : null;
            }

            foreach ($candidates as $id) {
                if ((is_int($id) || is_string($id)) && $id !== '' && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Plain text of all blocks, with a space between blocks so words don't merge.
     */
    public function plainText(): string
    {
        $registry = app(BlockRegistry::class);
        $parts = [];

        foreach ($this->blocks as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || ! $registry->has($block['type'])) {
                continue;
            }

            $blockType = $registry->get($block['type']);

            if (! $blockType instanceof ContentAwareBlock) {
                continue;
            }

            $text = $blockType->plainText(is_array($block['data'] ?? null) ? $block['data'] : []);

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{version: int, blocks: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'blocks' => array_values($this->blocks),
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '{"version":1,"blocks":[]}';
    }
}
