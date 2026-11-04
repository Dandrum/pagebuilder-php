<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;
use Dandrum\PageBuilder\Contracts\HtmlSanitizer;
use Dandrum\PageBuilder\Support\TextHelper;

final class ImageTextBlock implements BlockType, ContentAwareBlock
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

    public function normalize(array $data): ?array
    {
        $html = is_string($data['html'] ?? null) ? $data['html'] : '';
        $imageUrl = is_string($data['imageUrl'] ?? null) ? trim($data['imageUrl']) : '';

        $position = $data['imagePosition'] ?? 'left';
        $align = $data['align'] ?? 'left';

        return [
            'imageUrl' => $imageUrl,
            'imagePosition' => $position === 'right' ? 'right' : 'left',
            'html' => app(HtmlSanitizer::class)->sanitize($html),
            'align' => is_string($align) && in_array($align, ['left', 'center', 'right', 'justify'], true) ? $align : 'left',
        ];
    }

    public function plainText(array $data): string
    {
        return TextHelper::htmlToText(is_string($data['html'] ?? null) ? $data['html'] : '');
    }
}
