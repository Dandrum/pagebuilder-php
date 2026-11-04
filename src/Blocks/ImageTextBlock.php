<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Blocks\Concerns\NormalizesImageData;
use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;
use Dandrum\PageBuilder\Contracts\HtmlSanitizer;
use Dandrum\PageBuilder\Contracts\ImageBearingBlock;
use Dandrum\PageBuilder\Support\TextHelper;

/**
 * An image beside a rich text. The image part follows the image block (`media_id` or `imageUrl`
 * according to `pagebuilder.image.source`, plus alt/caption/credit); `imagePosition` is left|right.
 */
final class ImageTextBlock implements BlockType, ContentAwareBlock, ImageBearingBlock
{
    use NormalizesImageData;

    public static function key(): string
    {
        return 'image_text';
    }

    public function label(): string
    {
        return __('Image + Text');
    }

    public function imageUrlKey(): string
    {
        return 'imageUrl';
    }

    public function defaultData(): array
    {
        return [
            'media_id' => null,
            'imageUrl' => '',
            'alt' => '',
            'caption' => '',
            'credit' => '',
            'imagePosition' => 'left',
            'html' => '',
            'align' => 'left',
        ];
    }

    public function rules(): array
    {
        $max = (int) config('pagebuilder.max_html_length', 20000);

        return [
            'html' => ['nullable', 'string', 'max:'.$max],
            'imagePosition' => ['required', 'string', 'in:left,right'],
            'align' => ['nullable', 'string', 'in:left,center,right,justify'],
            'alt' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
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
        $html = app(HtmlSanitizer::class)->sanitize($html);

        $image = $this->normalizeImageData($data, 'imageUrl');

        if ($image === [] && TextHelper::isBlank($html)) {
            return null;
        }

        $align = $data['align'] ?? 'left';

        return $image + [
            'imagePosition' => ($data['imagePosition'] ?? 'left') === 'right' ? 'right' : 'left',
            'html' => $html,
            'align' => is_string($align) && in_array($align, ['left', 'center', 'right', 'justify'], true) ? $align : 'left',
        ];
    }

    public function plainText(array $data): string
    {
        return TextHelper::htmlToText(is_string($data['html'] ?? null) ? $data['html'] : '');
    }
}
