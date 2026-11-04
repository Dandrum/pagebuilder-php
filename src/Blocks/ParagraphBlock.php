<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;
use Dandrum\PageBuilder\Contracts\HtmlSanitizer;
use Dandrum\PageBuilder\Support\TextHelper;

final class ParagraphBlock implements BlockType, ContentAwareBlock
{
    private const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

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
        $max = (int) config('pagebuilder.max_html_length', 20000);

        return [
            'html' => ['nullable', 'string', 'max:'.$max],
            'align' => ['nullable', 'string', 'in:'.implode(',', self::ALIGNMENTS)],
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

    public function normalize(array $data): ?array
    {
        $html = is_string($data['html'] ?? null) ? $data['html'] : '';
        $html = app(HtmlSanitizer::class)->sanitize($html);

        if (TextHelper::isBlank($html)) {
            return null;
        }

        $align = $data['align'] ?? 'left';

        return [
            'html' => $html,
            'align' => is_string($align) && in_array($align, self::ALIGNMENTS, true) ? $align : 'left',
        ];
    }

    public function plainText(array $data): string
    {
        return TextHelper::htmlToText(is_string($data['html'] ?? null) ? $data['html'] : '');
    }
}
