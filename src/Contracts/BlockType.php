<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Contracts;

interface BlockType
{
    /**
     * Stable identifier stored as `blocks[].type` in the JSON document.
     */
    public static function key(): string;

    /**
     * Human-readable label shown in the "add block" menu.
     */
    public function label(): string;

    /**
     * Data a freshly added block of this type starts out with.
     *
     * @return array<string, mixed>
     */
    public function defaultData(): array;

    /**
     * Validation rules for this block's `data`, keyed by the data field name
     * (not the full `pageBuilderBlocks.{i}.data.` path — the trait prefixes that).
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array;

    /**
     * Blade view name rendered inside the editor for one block of this type.
     * Receives `block` (the full block array) and `index` (its position in
     * the bound component's blocks array).
     */
    public function editorView(): string;

    /**
     * Blade view name used by `<x-pagebuilder::render>` for one block of
     * this type. Receives `data` (the block's `data` array) and `block`.
     */
    public function renderView(): string;
}
