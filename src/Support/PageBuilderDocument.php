<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Support;

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

        return self::fromArray($decoded ?? []);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        return new self(
            version: (int) ($data['version'] ?? 1),
            blocks: $data['blocks'] ?? [],
        );
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
