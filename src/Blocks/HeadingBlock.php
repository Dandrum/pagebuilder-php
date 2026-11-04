<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;

final class HeadingBlock implements BlockType, ContentAwareBlock
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

    public function normalize(array $data): ?array
    {
        $text = is_string($data['text'] ?? null) ? trim($data['text']) : '';

        if ($text === '') {
            return null;
        }

        $align = $data['align'] ?? 'left';

        return [
            'level' => max(2, min(4, (int) ($data['level'] ?? 2))),
            'text' => $text,
            'align' => is_string($align) && in_array($align, ['left', 'center', 'right'], true) ? $align : 'left',
        ];
    }

    public function plainText(array $data): string
    {
        return is_string($data['text'] ?? null) ? trim($data['text']) : '';
    }
}
