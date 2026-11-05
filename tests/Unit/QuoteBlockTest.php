<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Unit;

use Dandrum\PageBuilder\Blocks\QuoteBlock;
use Dandrum\PageBuilder\Support\BlockRegistry;
use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Dandrum\PageBuilder\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

final class QuoteBlockTest extends TestCase
{
    public function test_it_keeps_only_the_text(): void
    {
        $this->assertSame(['text' => 'Hallo Welt'], (new QuoteBlock)->normalize(['text' => '  Hallo   Welt ', 'align' => 'center', 'author' => 'x']));
    }

    public function test_it_strips_tags_and_entity_encoded_tags(): void
    {
        $block = new QuoteBlock;

        $this->assertSame(['text' => 'Ein Zitatalert(1)'], $block->normalize(['text' => '<p>Ein <strong>Zitat</strong><script>alert(1)</script></p>']));
        $this->assertSame(['text' => 'alert(1)Zitat'], $block->normalize(['text' => '&lt;script&gt;alert(1)&lt;/script&gt;Zitat']));
    }

    public function test_it_collapses_newlines_and_whitespace(): void
    {
        $this->assertSame(['text' => 'a b c'], (new QuoteBlock)->normalize(['text' => "a\n\n  b\t\xC2\xA0c"]));
    }

    public function test_it_is_idempotent(): void
    {
        $first = (new QuoteBlock)->normalize(['text' => 'Tom &amp;amp; Jerry <b>x</b>']);

        $this->assertSame($first, (new QuoteBlock)->normalize($first));
    }

    public function test_it_drops_empty_or_invalid_text(): void
    {
        $block = new QuoteBlock;

        $this->assertNull($block->normalize(['text' => '']));
        $this->assertNull($block->normalize(['text' => " \n <b> </b> "]));
        $this->assertNull($block->normalize(['text' => ['x']]));
        $this->assertNull($block->normalize([]));
    }

    public function test_it_caps_the_length(): void
    {
        $out = (new QuoteBlock)->normalize(['text' => str_repeat('ä', 800)]);

        $this->assertSame(500, mb_strlen($out['text']));

        config()->set('pagebuilder.max_quote_length', 10);

        $this->assertSame(10, mb_strlen((new QuoteBlock)->normalize(['text' => str_repeat('x', 50)])['text']));
    }

    public function test_plain_text_returns_the_clean_text(): void
    {
        $this->assertSame('Ein Zitat', (new QuoteBlock)->plainText(['text' => '<i>Ein</i>  Zitat']));
        $this->assertSame('', (new QuoteBlock)->plainText([]));
    }

    public function test_rules_allow_empty_and_cap_the_length(): void
    {
        $rules = (new QuoteBlock)->rules();

        $this->assertTrue(Validator::make(['text' => null], $rules)->passes());
        $this->assertTrue(Validator::make(['text' => str_repeat('x', 500)], $rules)->passes());
        $this->assertTrue(Validator::make(['text' => str_repeat('x', 501)], $rules)->fails());
    }

    public function test_default_data_and_key(): void
    {
        $this->assertSame('quote', QuoteBlock::key());
        $this->assertSame(['text' => ''], (new QuoteBlock)->defaultData());
    }

    public function test_it_is_registered_and_normalized_inside_a_document(): void
    {
        $this->assertTrue(app(BlockRegistry::class)->has('quote'));

        $doc = (new PageBuilderDocument(1, [
            ['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'quote', 'data' => ['text' => '<b>Hi</b>']],
            ['id' => '22222222-2222-4222-8222-222222222222', 'type' => 'quote', 'data' => ['text' => ' ']],
        ]))->normalized();

        $this->assertCount(1, $doc->blocks);
        $this->assertSame(['text' => 'Hi'], $doc->blocks[0]['data']);
        $this->assertSame('Hi', $doc->plainText());
    }
}
