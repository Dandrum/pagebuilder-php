<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;

final class ImageTextBlock implements BlockType
{
    public static function key(): string
    {
        return 'image_text';
    }

    public function label(): string
    {
        return __('Image + Text');
    }

    public function defaultData(): array
    {
        return [
            'imageUrl' => '',
            'imagePosition' => 'left',
            'html' => '',
            'align' => 'left',
        ];
    }

    public function rules(): array
    {
        return [
            'imageUrl' => ['required', 'string'],
            'imagePosition' => ['required', 'string', 'in:left,right'],
            'html' => ['required', 'string'],
            'align' => ['required', 'string', 'in:left,center,right,justify'],
        ];
    }

    public function editorView(): string
    {
        return 'pagebuilder::blocks.edit.image-text';
    }

    public function renderView(): string
    {
        return 'pagebuilder::blocks.render.image-text';
    }
}
