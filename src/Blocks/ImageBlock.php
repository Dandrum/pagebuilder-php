<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;

final class ImageBlock implements BlockType
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
            'url' => '',
            'alt' => '',
            'caption' => '',
        ];
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string'],
            'alt' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
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
}
