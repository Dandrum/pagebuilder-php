<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Unit;

use Dandrum\PageBuilder\Blocks\GalleryBlock;
use Dandrum\PageBuilder\Support\BlockRegistry;
use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Dandrum\PageBuilder\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

final class GalleryBlockTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';

    private const B = '22222222-2222-4222-8222-222222222222';

    public function test_it_is_registered_and_starts_empty(): void
    {
        $this->assertTrue(app(BlockRegistry::class)->has('gallery'));
        $this->assertSame(['slideshow' => false, 'items' => []], (new GalleryBlock)->defaultData());
        $this->assertSame('', (new GalleryBlock)->plainText(['items' => [['id' => self::A, 'media_id' => 1, 'caption' => 'Text']]]));
    }

    public function test_it_keeps_order_media_ids_and_captions(): void
    {
        $data = (new GalleryBlock)->normalize(['slideshow' => true, 'items' => [
            ['id' => self::A, 'media_id' => '12', 'caption' => '  Erstes '],
            ['id' => self::B, 'media_id' => 15],
        ]]);

        $this->assertSame([
            'slideshow' => true,
            'items' => [
                ['id' => self::A, 'media_id' => 12, 'caption' => 'Erstes'],
                ['id' => self::B, 'media_id' => 15],
            ],
        ], $data);
    }

    public function test_it_drops_empty_items_and_unknown_keys(): void
    {
        $data = (new GalleryBlock)->normalize([
            'evil' => 'x',
            'items' => [
                ['id' => self::A, 'media_id' => null, 'url' => '', 'caption' => 'ohne Bild'],
                'kaputt',
                ['id' => self::B, 'media_id' => 3, 'onclick' => 'x', 'html' => '<b>'],
            ],
        ]);

        $this->assertSame(['slideshow' => false, 'items' => [['id' => self::B, 'media_id' => 3]]], $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('emptyGalleries')]
    public function test_a_gallery_without_images_is_dropped(array $data): void
    {
        $this->assertNull((new GalleryBlock)->normalize($data));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function emptyGalleries(): array
    {
        return [
            'no items key' => [['slideshow' => true]],
            'empty list' => [['items' => []]],
            'items not an array' => [['items' => 'abc']],
            'only empty slots' => [['items' => [['id' => self::A, 'media_id' => null, 'url' => '', 'alt' => '']]]],
        ];
    }

    #[DataProvider('slideshowValues')]
    public function test_the_slideshow_switch_is_cast_to_a_bool(mixed $value, bool $expected): void
    {
        $data = (new GalleryBlock)->normalize(['slideshow' => $value, 'items' => [['media_id' => 1]]]);

        $this->assertSame($expected, $data['slideshow']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function slideshowValues(): array
    {
        return [
            'string 1' => ['1', true],
            'string true' => ['true', true],
            'bool true' => [true, true],
            'int 0' => [0, false],
            'garbage' => ['abc', false],
            'null' => [null, false],
        ];
    }

    public function test_it_caps_the_list_at_the_configured_maximum(): void
    {
        config()->set('pagebuilder.gallery.max_images', 3);

        $items = array_map(fn (int $n) => ['media_id' => $n], range(1, 6));
        $data = (new GalleryBlock)->normalize(['items' => $items]);

        $this->assertSame([1, 2, 3], array_column($data['items'], 'media_id'));
        $this->assertSame(3, GalleryBlock::maxImages());
    }

    public function test_missing_and_duplicate_item_ids_are_replaced_by_unique_uuids(): void
    {
        $data = (new GalleryBlock)->normalize(['items' => [
            ['id' => self::A, 'media_id' => 1],
            ['id' => self::A, 'media_id' => 2],
            ['media_id' => 3],
            ['id' => '  ', 'media_id' => 4],
        ]]);

        $ids = array_column($data['items'], 'id');

        $this->assertSame(self::A, $ids[0]);
        $this->assertCount(4, array_unique($ids));

        foreach ($ids as $id) {
            $this->assertNotSame('', $id);
        }
    }

    public function test_normalizing_twice_changes_nothing(): void
    {
        $first = (new GalleryBlock)->normalize(['slideshow' => '1', 'items' => [
            ['media_id' => 1, 'caption' => ' x '],
            ['id' => self::A, 'url' => '/a.jpg', 'alt' => 'A'],
        ]]);

        $this->assertSame($first, (new GalleryBlock)->normalize($first));
    }

    #[DataProvider('imageUrls')]
    public function test_url_items_only_keep_safe_urls(string $url, bool $kept): void
    {
        $data = (new GalleryBlock)->normalize(['items' => [['url' => $url, 'alt' => 'x']]]);

        $this->assertSame($kept, $data !== null);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function imageUrls(): array
    {
        return [
            'https' => ['https://example.com/a.jpg', true],
            'relative path' => ['/media/a.jpg', true],
            'javascript' => ['javascript:alert(1)', false],
            'protocol relative' => ['//evil.example/a.jpg', false],
            'data uri' => ['data:image/png;base64,AAAA', false],
        ];
    }

    public function test_the_rules_bound_the_list_and_the_switch(): void
    {
        config()->set('pagebuilder.gallery.max_images', 2);
        $rules = (new GalleryBlock)->rules();

        $this->assertTrue(Validator::make(['items' => [[], []], 'slideshow' => true], $rules)->passes());
        $this->assertTrue(Validator::make(['items' => [[], [], []]], $rules)->fails());
        $this->assertTrue(Validator::make(['slideshow' => 'abc'], $rules)->fails());
    }

    public function test_the_document_normalizes_a_gallery_block_and_drops_an_empty_one(): void
    {
        $doc = (new PageBuilderDocument(1, [
            ['id' => 'g1', 'type' => 'gallery', 'data' => ['items' => [['media_id' => 1]]]],
            ['id' => 'g2', 'type' => 'gallery', 'data' => ['items' => []]],
        ]))->normalized();

        $this->assertCount(1, $doc->blocks);
        $this->assertSame('g1', $doc->blocks[0]['id']);
    }
}
