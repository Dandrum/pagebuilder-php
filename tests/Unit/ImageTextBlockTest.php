<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Unit;

use Dandrum\PageBuilder\Blocks\ImageTextBlock;
use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Dandrum\PageBuilder\Tests\TestCase;

final class ImageTextBlockTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';

    private const B = '22222222-2222-4222-8222-222222222222';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function normalize(array $data): ?array
    {
        return (new ImageTextBlock)->normalize($data);
    }

    public function test_it_keeps_media_source_text_and_position(): void
    {
        $out = $this->normalize([
            'media_id' => '7',
            'caption' => ' Eigene ',
            'imagePosition' => 'right',
            'html' => '<div>Text <strong>fett</strong></div>',
            'align' => 'justify',
            'junk' => 'x',
        ]);

        $this->assertSame([
            'media_id' => 7,
            'caption' => 'Eigene',
            'imagePosition' => 'right',
            'html' => '<div>Text <strong>fett</strong></div>',
            'align' => 'justify',
        ], $out);
    }

    public function test_it_keeps_a_safe_image_url_with_alt(): void
    {
        $out = $this->normalize(['imageUrl' => '/bild.jpg', 'alt' => 'Alt', 'html' => '<div>x</div>']);

        $this->assertSame('/bild.jpg', $out['imageUrl']);
        $this->assertSame('Alt', $out['alt']);
    }

    public function test_an_unsafe_image_url_is_dropped(): void
    {
        $out = $this->normalize(['imageUrl' => 'javascript:alert(1)', 'alt' => 'x', 'html' => '<div>Text</div>']);

        $this->assertArrayNotHasKey('imageUrl', $out);
        $this->assertArrayNotHasKey('alt', $out);
    }

    public function test_the_position_is_whitelisted(): void
    {
        $this->assertSame('left', $this->normalize(['media_id' => 1, 'imagePosition' => 'top'])['imagePosition']);
        $this->assertSame('left', $this->normalize(['media_id' => 1])['imagePosition']);
        $this->assertSame('right', $this->normalize(['media_id' => 1, 'imagePosition' => 'right'])['imagePosition']);
    }

    public function test_the_align_is_whitelisted(): void
    {
        foreach (['left', 'center', 'right', 'justify'] as $align) {
            $this->assertSame($align, $this->normalize(['media_id' => 1, 'align' => $align])['align']);
        }

        $this->assertSame('left', $this->normalize(['media_id' => 1, 'align' => 'diagonal'])['align']);
        $this->assertSame('left', $this->normalize(['media_id' => 1, 'align' => ['center']])['align']);
        $this->assertSame('left', $this->normalize(['media_id' => 1])['align']);
    }

    public function test_the_html_is_sanitized(): void
    {
        $out = $this->normalize([
            'media_id' => 1,
            'html' => '<h2 onclick="x()">Titel</h2><script>alert(1)</script><blockquote>Zitat</blockquote><img src="x" onerror="y()">',
        ]);

        $this->assertStringContainsString('<h2>Titel</h2>', $out['html']);
        $this->assertStringContainsString('<blockquote>Zitat</blockquote>', $out['html']);
        $this->assertStringNotContainsString('script', $out['html']);
        $this->assertStringNotContainsString('onclick', $out['html']);
        $this->assertStringNotContainsString('<img', $out['html']);
    }

    public function test_an_image_without_text_is_kept_and_text_without_an_image_too(): void
    {
        $this->assertSame('', $this->normalize(['media_id' => 3, 'html' => '<p></p>'])['html']);
        $this->assertSame(['imagePosition', 'html', 'align'], array_keys($this->normalize(['html' => '<div>nur Text</div>']) ?? []));
    }

    public function test_an_empty_block_is_dropped(): void
    {
        $this->assertNull($this->normalize(['html' => '<p>&nbsp;</p>', 'imageUrl' => '']));
    }

    public function test_the_plain_text_covers_the_text_only(): void
    {
        $block = new ImageTextBlock;

        $this->assertSame('Titel Zitat Ende', $block->plainText(['html' => '<h2>Titel</h2><blockquote>Zitat</blockquote><div>Ende</div>', 'alt' => 'ignoriert']));
        $this->assertSame('', $block->plainText([]));
    }

    public function test_the_document_collects_its_media_id_and_plain_text(): void
    {
        $document = new PageBuilderDocument(1, [
            ['id' => self::A, 'type' => 'image_text', 'data' => ['media_id' => 5, 'html' => '<div>Eins</div>']],
            ['id' => self::B, 'type' => 'image', 'data' => ['media_id' => 5]],
            ['id' => 'c', 'type' => 'image_text', 'data' => ['media_id' => 9, 'html' => '<div>Zwei</div>']],
        ]);

        $this->assertSame([5, 9], $document->mediaIds());
        $this->assertSame('Eins Zwei', $document->plainText());
    }

    public function test_the_document_normalizes_the_block(): void
    {
        $document = (new PageBuilderDocument(1, [
            ['id' => self::A, 'type' => 'image_text', 'data' => ['media_id' => 5, 'html' => '<div>a<script>1</script></div>', 'x' => 1]],
            ['id' => self::B, 'type' => 'image_text', 'data' => ['html' => '']],
        ]))->normalized();

        $this->assertCount(1, $document->blocks);
        $this->assertSame(['media_id', 'imagePosition', 'html', 'align'], array_keys($document->blocks[0]['data']));
        $this->assertStringNotContainsString('script', $document->blocks[0]['data']['html']);
    }

    public function test_plain_text_separates_headings_and_quotes(): void
    {
        $document = new PageBuilderDocument(1, [
            ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '<h2>Titel</h2><blockquote>Zitat</blockquote>Ende']],
        ]);

        $this->assertSame('Titel Zitat Ende', $document->plainText());
    }
}
