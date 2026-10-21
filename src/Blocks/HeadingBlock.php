<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;

final class HeadingBlock implements BlockType
{
    public static function key(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return __('Heading');
    }

    public function defaultData(): array
    {
        return [
            'level' => 2,
            'text' => '',
            'align' => 'left',
        ];
    }

    public function rules(): array
    {
        return [
            'level' => ['required', 'integer', 'in:2,3,4'],
            'text' => ['required', 'string', 'max:150'],
            'align' => ['required', 'string', 'in:left,center,right'],
        ];
    }

    public function editorView(): string
    {
        return 'pagebuilder::blocks.edit.heading';
    }

    public function renderView(): string
    {
        return 'pagebuilder::blocks.render.heading';
    }
}
