<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Unit;

use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Dandrum\PageBuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PageBuilderDocumentTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';

    private const B = '22222222-2222-4222-8222-222222222222';

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    private function block(string $id, string $type, array $data): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data];
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function emptyJson(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
            'invalid json' => ['{nope'],
            'scalar json' => ['42'],
            'blocks not an array' => ['{"version":1,"blocks":"x"}'],
        ];
    }

    #[DataProvider('emptyJson')]
    public function test_from_json_tolerates_garbage(?string $json): void
    {
        $this->assertSame([], PageBuilderDocument::fromJson($json)->blocks);
    }

    public function test_from_json_reads_a_document(): void
    {
        $doc = PageBuilderDocument::fromJson('{"version":1,"blocks":[{"id":"x","type":"paragraph","data":{}}]}');

        $this->assertCount(1, $doc->blocks);
    }

    public function test_normalized_drops_unknown_malformed_and_duplicate_blocks(): void
    {
        $doc = new PageBuilderDocument(1, [
            $this->block(self::A, 'paragraph', ['html' => '<p>one</p>']),
            $this->block(self::A, 'paragraph', ['html' => '<p>duplicate id</p>']),
            $this->block(self::B, 'quote', ['html' => '<p>unregistered type</p>']),
            'not an array',
            ['id' => 5, 'type' => 'paragraph', 'data' => []],
            ['id' => 'c', 'type' => 'paragraph', 'data' => 'nope'],
        ]);

        $normalized = $doc->normalized();

        $this->assertCount(1, $normalized->blocks);
        $this->assertSame('<p>one</p>', $normalized->blocks[0]['data']['html']);
    }

    public function test_normalized_sanitizes_and_whitelists_paragraph_data(): void
    {
        $doc = (new PageBuilderDocument(1, [
            $this->block(self::A, 'paragraph', ['html' => '<p onclick="x()">Hi<script>alert(1)</script></p>', 'align' => 'bogus', 'extra' => 'dropped']),
        ]))->normalized();

        $this->assertSame(['html' => '<p>Hi</p>', 'align' => 'left'], $doc->blocks[0]['data']);
    }

    public function test_paragraph_align_keeps_valid_values_and_resets_tampered_ones(): void
    {
        foreach (['left', 'center', 'right', 'justify'] as $align) {
            $doc = (new PageBuilderDocument(1, [$this->block(self::A, 'paragraph', ['html' => '<p>x</p>', 'align' => $align])]))->normalized();
            $this->assertSame($align, $doc->blocks[0]['data']['align']);
        }

        foreach (['CENTER', 'middle', 5, null, ['center']] as $bad) {
            $doc = (new PageBuilderDocument(1, [$this->block(self::A, 'paragraph', ['html' => '<p>x</p>', 'align' => $bad])]))->normalized();
            $this->assertSame('left', $doc->blocks[0]['data']['align']);
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function emptyParagraphs(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ["  \n "],
            'empty p' => ['<p></p>'],
            'nbsp' => ['<p>&nbsp;</p>'],
            'raw nbsp' => ["<div>\u{00A0}</div>"],
            'br only' => ['<div><br></div>'],
            'tags only' => ['<div> <br> </div><p> </p>'],
        ];
    }

    #[DataProvider('emptyParagraphs')]
    public function test_normalized_drops_empty_paragraphs(string $html): void
    {
        $doc = (new PageBuilderDocument(1, [$this->block(self::A, 'paragraph', ['html' => $html])]))->normalized();

        $this->assertSame([], $doc->blocks);
    }

    public function test_normalized_is_idempotent(): void
    {
        $doc = (new PageBuilderDocument(1, [
            $this->block(self::A, 'paragraph', ['html' => '<div>Hi <b>x</b></div>']),
            $this->block(self::B, 'image', ['media_id' => '12', 'caption' => ' c ']),
        ]))->normalized();

        $this->assertSame($doc->toArray(), $doc->normalized()->toArray());
    }

    public function test_normalized_casts_media_id_and_whitelists_image_keys(): void
    {
        $doc = (new PageBuilderDocument(1, [
            $this->block(self::A, 'image', ['media_id' => '12', 'caption' => ' Unterschrift ', 'alt' => '', 'x' => 1, 'url' => 'javascript:alert(1)']),
        ]))->normalized();

        $this->assertSame(['media_id' => 12, 'caption' => 'Unterschrift'], $doc->blocks[0]['data']);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function imageUrls(): array
    {
        return [
            'https' => ['https://example.com/a.jpg', true],
            'http' => ['http://example.com/a.jpg', true],
            'relative path' => ['/storage/a.jpg', true],
            'javascript' => ['javascript:alert(1)', false],
            'data uri' => ['data:image/png;base64,AAAA', false],
            'protocol relative' => ['//evil.example/a.jpg', false],
        ];
    }

    #[DataProvider('imageUrls')]
    public function test_image_urls_are_restricted(string $url, bool $kept): void
    {
        $doc = (new PageBuilderDocument(1, [$this->block(self::A, 'image', ['url' => $url])]))->normalized();

        $this->assertCount($kept ? 1 : 0, $doc->blocks);
    }

    public function test_an_image_without_source_is_dropped(): void
    {
        $doc = (new PageBuilderDocument(1, [$this->block(self::A, 'image', ['alt' => 'only alt', 'media_id' => null])]))->normalized();

        $this->assertSame([], $doc->blocks);
    }

    public function test_heading_level_is_clamped(): void
    {
        $doc = (new PageBuilderDocument(1, [$this->block(self::A, 'heading', ['level' => 9, 'text' => ' Titel '])]))->normalized();

        $this->assertSame(['level' => 4, 'text' => 'Titel', 'align' => 'left'], $doc->blocks[0]['data']);
    }

    public function test_media_ids_are_distinct_and_non_empty(): void
    {
        $doc = new PageBuilderDocument(1, [
            $this->block(self::A, 'image', ['media_id' => 3]),
            $this->block(self::B, 'image', ['media_id' => 3]),
            $this->block('c', 'image', ['media_id' => null]),
            $this->block('d', 'image', ['media_id' => '']),
            $this->block('e', 'image', ['media_id' => 5]),
            $this->block('f', 'paragraph', ['html' => '<p>x</p>']),
        ]);

        $this->assertSame([3, 5], $doc->mediaIds());
    }

    public function test_plain_text_does_not_merge_words_between_blocks(): void
    {
        $doc = new PageBuilderDocument(1, [
            $this->block(self::A, 'paragraph', ['html' => '<p>Erster</p><p>Absatz&nbsp;mit &amp; Zeichen</p>']),
            $this->block(self::B, 'paragraph', ['html' => '<ul><li>eins</li><li>zwei</li></ul>']),
            $this->block('c', 'image', ['media_id' => 1, 'alt' => 'ignored']),
            $this->block('d', 'paragraph', ['html' => '<div>Zeile<br>zwei</div>']),
        ]);

        $this->assertSame('Erster Absatz mit & Zeichen eins zwei Zeile zwei', $doc->plainText());
    }

    public function test_plain_text_of_an_empty_document_is_empty(): void
    {
        $this->assertSame('', (new PageBuilderDocument(1, []))->plainText());
    }
}
