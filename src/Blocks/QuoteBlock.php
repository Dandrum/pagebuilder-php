<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Blocks;

use Dandrum\PageBuilder\Contracts\BlockType;
use Dandrum\PageBuilder\Contracts\ContentAwareBlock;

/**
 * Pull quote: one plain text, no markup and no styling options.
 */
final class QuoteBlock implements BlockType, ContentAwareBlock
{
    public const DEFAULT_MAX_LENGTH = 500;

    public static function key(): string
    {
        return 'quote';
    }

    public static function maxLength(): int
    {
        return max(1, (int) config('pagebuilder.max_quote_length', self::DEFAULT_MAX_LENGTH));
    }

    public function label(): string
    {
        return __('Quote');
    }

    public function defaultData(): array
    {
        return ['text' => ''];
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:'.self::maxLength()],
        ];
    }

    public function editorView(): string
    {
        return 'pagebuilder::blocks.edit.quote';
    }

    public function renderView(): string
    {
        return 'pagebuilder::blocks.render.quote';
    }

    public function normalize(array $data): ?array
    {
        $text = self::clean($data['text'] ?? null);

        if ($text === '') {
            return null;
        }

        return ['text' => $text];
    }

    public function plainText(array $data): string
    {
        return self::clean($data['text'] ?? null);
    }

    /**
     * Plain text only: tags and entity-encoded tags are removed, whitespace is collapsed, the
     * result is capped. Repeats until stable so the result is idempotent.
     */
    private static function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        for ($i = 0; $i < 5; $i++) {
            $previous = $value;
            $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($value === $previous) {
                break;
            }
        }

        $value = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? $value);

        return trim(mb_substr($value, 0, self::maxLength()));
    }
}
