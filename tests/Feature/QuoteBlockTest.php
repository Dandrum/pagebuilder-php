<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Feature;

use Dandrum\PageBuilder\Tests\Fixtures\EditorHost;
use Dandrum\PageBuilder\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

final class QuoteBlockTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        view()->share('errors', new ViewErrorBag);
    }

    public function test_the_render_view_outputs_a_figure_with_an_escaped_blockquote(): void
    {
        $html = Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => ['version' => 1, 'blocks' => [
            ['id' => 'a', 'type' => 'quote', 'data' => ['text' => '<script>alert(1)</script> & "Hi"']],
        ]]]);

        $this->assertStringContainsString('<figure class="pb-block pb-block--quote">', $html);
        $this->assertStringContainsString('<blockquote class="pb-quote"><p>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Hi&quot;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_the_editor_view_has_a_textarea_and_the_insert_pill(): void
    {
        $html = Blade::render('<x-pagebuilder::editor :blocks="$blocks" :block-types="$types" />', [
            'blocks' => [['id' => self::A, 'type' => 'quote', 'data' => ['text' => 'Hallo']]],
            'types' => [['key' => 'quote', 'label' => 'Quote']],
        ]);

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('wire:model.blur="pageBuilderBlocks.0.data.text"', $html);
        $this->assertStringContainsString('maxlength="500"', $html);
        $this->assertStringContainsString('aria-label="Quote text"', $html);
        $this->assertStringContainsString('placeholder="Enter quote …"', $html);
        $this->assertStringContainsString('pb-quote-input', $html);
        $this->assertStringContainsString('wire:click="insertPageBuilderBlock(\'quote\', \'\')"', $html);
        $this->assertStringContainsString('aria-label="Insert Quote at the start"', $html);
    }

    public function test_the_german_texts_exist(): void
    {
        app()->setLocale('de');

        $html = Blade::render('<x-pagebuilder::editor :blocks="$blocks" :block-types="[]" />', [
            'blocks' => [['id' => self::A, 'type' => 'quote', 'data' => ['text' => '']]],
        ]);

        $this->assertStringContainsString('placeholder="Zitat eingeben …"', $html);
        $this->assertStringContainsString('aria-label="Zitattext"', $html);
        $this->assertStringContainsString('Zitat', $html);
    }

    public function test_inserting_a_quote_block_starts_with_empty_text(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => ['blocks' => []]])
            ->call('insertPageBuilderBlock', 'quote', '');

        $this->assertSame('quote', $component->get('pageBuilderBlocks.0.type'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.text'));
    }

    public function test_a_too_long_quote_gets_the_quote_message(): void
    {
        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'quote', 'data' => ['text' => str_repeat('x', 501)]]]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.text' => 'max']);
    }

    public function test_the_saved_document_has_the_clean_text_and_drops_empty_quotes(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => ['blocks' => [
            ['id' => self::A, 'type' => 'quote', 'data' => ['text' => '<b>Hi</b>  da']],
            ['id' => '22222222-2222-4222-8222-222222222222', 'type' => 'quote', 'data' => ['text' => '']],
        ]]]);

        $document = $component->instance()->getPageBuilderDocumentArray();

        $this->assertCount(1, $document['blocks']);
        $this->assertSame(['text' => 'Hi da'], $document['blocks'][0]['data']);
    }
}
