<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;

final class ParagraphBlock implements BlockType
{
    public static function key(): string
    {
        return 'paragraph';
    }

    public function label(): string
    {
        return __('Paragraph');
    }

    public function defaultData(): array
    {
        return [
            'html' => '',
            'align' => 'left',
        ];
    }

    public function rules(): array
    {
        return [
            'html' => ['required', 'string'],
            'align' => ['required', 'string', 'in:left,center,right,justify'],
        ];
    }

    public function editorView(): string
    {
        return 'pagebuilder::blocks.edit.paragraph';
    }

    public function renderView(): string
    {
        return 'pagebuilder::blocks.render.paragraph';
    }
}
