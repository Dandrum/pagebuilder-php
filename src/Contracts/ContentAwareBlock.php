<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Contracts;

/**
 * Optional companion to {@see BlockType}: lets a block clean its own stored data
 * (whitelisting keys, sanitizing HTML) and expose a plain-text form.
 */
interface ContentAwareBlock
{
    /**
     * Returns the cleaned `data`, or null when the block is empty and should be dropped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function normalize(array $data): ?array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function plainText(array $data): string;
}
