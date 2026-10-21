<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Support;

use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Exceptions\UnknownBlockTypeException;
use InvalidArgumentException;

final class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $blocks = [];

    /**
     * @param  class-string  $blockClass
     */
    public function register(string $blockClass): void
    {
        if (! is_subclass_of($blockClass, BlockType::class)) {
            throw new InvalidArgumentException("[{$blockClass}] must implement ".BlockType::class);
        }

        /** @var BlockType $block */
        $block = new $blockClass;

        $this->blocks[$blockClass::key()] = $block;
    }

    public function has(string $key): bool
    {
        return isset($this->blocks[$key]);
    }

    public function get(string $key): BlockType
    {
        return $this->blocks[$key] ?? throw new UnknownBlockTypeException($key);
    }

    /**
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        return $this->blocks;
    }
}
