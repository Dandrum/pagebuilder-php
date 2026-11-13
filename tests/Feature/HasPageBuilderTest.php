<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Feature;

use Dandrum\PageBuilder\Contracts\ImageMediaResolver;
use Dandrum\PageBuilder\Exceptions\UnknownBlockTypeException;
use Dandrum\PageBuilder\Tests\Fixtures\ArrayMediaResolver;
use Dandrum\PageBuilder\Tests\Fixtures\EditorHost;
use Dandrum\PageBuilder\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

final class HasPageBuilderTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';

    private const B = '22222222-2222-4222-8222-222222222222';

    private const C = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        EditorHost::$stored = [];
        ArrayMediaResolver::reset();
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function useMediaSource(array $image = []): void
    {
        config()->set('pagebuilder.image', array_replace([
            'source' => 'media',
            'upload' => 'deferred',
            'picker' => true,
            'upload_required' => ['alt', 'caption', 'credit'],
        ], $image));

        $this->app->singleton(ImageMediaResolver::class, ArrayMediaResolver::class);
    }

    /**
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    private function document(): array
    {
        return [
            'version' => 1,
            'blocks' => [
                ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '<p>eins</p>']],
                ['id' => self::B, 'type' => 'paragraph', 'data' => ['html' => '<p>zwei</p>', 'align' => 'left']],
                ['id' => self::C, 'type' => 'image', 'data' => ['media_id' => 1]],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function ids(mixed $component): array
    {
        return array_column($component->get('pageBuilderBlocks'), 'id');
    }

    public function test_initialize_merges_defaults_over_stored_data(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()]);

        $this->assertSame('left', $component->get('pageBuilderBlocks.0.data.align'));
        $this->assertSame('', $component->get('pageBuilderBlocks.2.data.url'));
        $this->assertSame(1, $component->get('pageBuilderBlocks.2.data.media_id'));
    }

    public function test_initialize_without_content_starts_empty(): void
    {
        $this->assertSame([], Livewire::test(EditorHost::class)->get('pageBuilderBlocks'));
    }

    public function test_insert_at_the_start_and_after_a_block(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('insertPageBuilderBlock', 'image', '')
            ->call('insertPageBuilderBlock', 'paragraph', self::A);

        $types = array_column($component->get('pageBuilderBlocks'), 'type');
        $ids = $this->ids($component);

        $this->assertSame(['image', 'paragraph', 'paragraph', 'paragraph', 'image'], $types);
        $this->assertSame(self::A, $ids[1]);
        $this->assertSame('', $component->get('pageBuilderBlocks.2.data.html'));
    }

    public function test_insert_with_an_unregistered_type_throws(): void
    {
        $this->expectException(UnknownBlockTypeException::class);

        Livewire::test(EditorHost::class)->call('insertPageBuilderBlock', 'carousel');
    }

    /**
     * @return array<string, array{0: int, 1: list<string>}>
     */
    public static function sortPositions(): array
    {
        return [
            'to the front' => [0, [self::C, self::A, self::B]],
            'to the middle' => [1, [self::A, self::C, self::B]],
            'to the end' => [2, [self::A, self::B, self::C]],
            'beyond the end is clamped' => [99, [self::A, self::B, self::C]],
            'below zero is clamped' => [-5, [self::C, self::A, self::B]],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('sortPositions')]
    public function test_sort_moves_a_block_and_clamps_the_position(int $position, array $expected): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('sortPageBuilderBlock', self::C, $position);

        $this->assertSame($expected, $this->ids($component));
    }

    public function test_sort_with_an_unknown_id_does_nothing(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('sortPageBuilderBlock', 'nope', 0);

        $this->assertSame([self::A, self::B, self::C], $this->ids($component));
    }

    public function test_sort_keeps_the_block_data_with_the_block(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('sortPageBuilderBlock', self::A, 1);

        $this->assertSame([self::B, self::A, self::C], $this->ids($component));
        $this->assertSame('<p>eins</p>', $component->get('pageBuilderBlocks.1.data.html'));
    }

    public function test_moving_at_the_edges_does_nothing(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('movePageBuilderBlockUp', self::A)
            ->call('movePageBuilderBlockDown', self::C);

        $this->assertSame([self::A, self::B, self::C], $this->ids($component));
    }

    public function test_moving_swaps_neighbours(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('movePageBuilderBlockDown', self::A)
            ->call('movePageBuilderBlockUp', self::C);

        $this->assertSame([self::B, self::C, self::A], $this->ids($component));
    }

    public function test_duplicate_gets_a_new_id(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('duplicatePageBuilderBlock', self::A);

        $ids = $this->ids($component);

        $this->assertCount(4, $ids);
        $this->assertSame(self::A, $ids[0]);
        $this->assertNotSame(self::A, $ids[1]);
        $this->assertSame('<p>eins</p>', $component->get('pageBuilderBlocks.1.data.html'));
    }

    public function test_remove_drops_the_block_and_its_pending_upload(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::C, UploadedFile::fake()->image('a.jpg'));

        $this->assertArrayHasKey(self::C, $component->get('pageBuilderUploads'));

        $component->call('removePageBuilderBlock', self::C);

        $this->assertSame([self::A, self::B], $this->ids($component));
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_picking_a_valid_media_id_sets_it_and_clears_the_metadata(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => ['caption' => 'old', 'alt' => 'old']]]],
        ])->dispatch('pagebuilder-image-picked', blockId: self::A, mediaId: 2);

        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.media_id'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.caption'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.alt'));
        $component->assertHasNoErrors();
    }

    public function test_picking_an_unknown_media_id_sets_an_error_and_does_not_assign(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => ['media_id' => 1]]]],
        ])->dispatch('pagebuilder-image-picked', blockId: self::A, mediaId: 999);

        $this->assertSame(1, $component->get('pageBuilderBlocks.0.data.media_id'));
        $component->assertHasErrors(['pageBuilderBlocks.0.data.media_id']);
    }

    public function test_picking_ignores_unknown_blocks_and_non_image_blocks(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->dispatch('pagebuilder-image-picked', blockId: 'other-editor', mediaId: 2)
            ->dispatch('pagebuilder-image-picked', blockId: self::A, mediaId: 2);

        $this->assertSame(1, $component->get('pageBuilderBlocks.2.data.media_id'));
        $this->assertArrayNotHasKey('media_id', $component->get('pageBuilderBlocks.0.data'));
    }

    public function test_picking_is_ignored_for_the_url_source(): void
    {
        $component = Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => []]]],
        ])->dispatch('pagebuilder-image-picked', blockId: self::A, mediaId: 1);

        $this->assertNull($component->get('pageBuilderBlocks.0.data.media_id'));
    }

    public function test_clearing_an_image_keeps_the_block(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->call('clearPageBuilderImage', self::C);

        $this->assertNull($component->get('pageBuilderBlocks.2.data.media_id'));
        $this->assertCount(3, $component->get('pageBuilderBlocks'));
    }

    public function test_deferred_upload_is_not_stored_until_the_host_asks(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::C, UploadedFile::fake()->image('foto.jpg'));

        $this->assertSame([], EditorHost::$stored);
        $this->assertNull($component->get('pageBuilderBlocks.2.data.media_id'));
        $this->assertArrayHasKey(self::C, $component->get('pageBuilderUploads'));

        $component->call('storePending');

        $this->assertSame(['foto.jpg'], EditorHost::$stored);
        $this->assertSame(2, $component->get('pageBuilderBlocks.2.data.media_id'));
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_deferred_mode_rejects_non_images_immediately(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::C, UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'));

        $component->assertHasErrors(['pageBuilderUploads.'.self::C]);
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_immediate_mode_validates_before_storing(): void
    {
        config()->set('pagebuilder.image', ['source' => 'media', 'upload' => 'immediate']);
        $this->app->singleton(ImageMediaResolver::class, ArrayMediaResolver::class);

        Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::C, UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['pageBuilderUploads.'.self::C]);

        $this->assertSame([], EditorHost::$stored);
    }

    public function test_immediate_mode_stores_a_valid_image_right_away(): void
    {
        config()->set('pagebuilder.image', ['source' => 'media', 'upload' => 'immediate']);
        $this->app->singleton(ImageMediaResolver::class, ArrayMediaResolver::class);

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::C, UploadedFile::fake()->image('sofort.jpg'));

        $this->assertSame(['sofort.jpg'], EditorHost::$stored);
        $this->assertSame(2, $component->get('pageBuilderBlocks.2.data.media_id'));
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_upload_off_ignores_a_tampered_upload(): void
    {
        $this->useMediaSource(['upload' => 'off']);

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::C, UploadedFile::fake()->image('a.jpg'));

        $this->assertSame([], $component->get('pageBuilderUploads'));
        $this->assertSame([], EditorHost::$stored);
        $this->assertSame(1, $component->get('pageBuilderBlocks.2.data.media_id'));
    }

    public function test_an_upload_for_a_paragraph_block_is_ignored(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, ['initial' => $this->document()])
            ->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('a.jpg'));

        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_rules_require_media_without_media_and_without_upload(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => []]]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.media_id' => 'required']);
    }

    public function test_rules_require_the_configured_metadata_with_an_upload(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => []]]],
        ])->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('a.jpg'))
            ->call('validateAll')
            ->assertHasErrors([
                'pageBuilderBlocks.0.data.alt' => 'required',
                'pageBuilderBlocks.0.data.caption' => 'required',
                'pageBuilderBlocks.0.data.credit' => 'required',
            ])
            ->assertHasNoErrors(['pageBuilderBlocks.0.data.media_id']);
    }

    public function test_rules_pass_with_an_upload_and_complete_metadata(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => ['alt' => 'a', 'caption' => 'c', 'credit' => 'p']]]],
        ])->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('a.jpg'))
            ->call('validateAll')
            ->assertHasNoErrors();
    }

    public function test_rules_fail_for_a_media_id_the_resolver_does_not_know(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => ['media_id' => 404]]]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.media_id']);
    }

    public function test_rules_require_url_and_alt_for_the_url_source(): void
    {
        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => self::A, 'type' => 'image', 'data' => []]]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.url' => 'required', 'pageBuilderBlocks.0.data.alt' => 'required']);
    }

    public function test_rules_reject_types_outside_the_registry_and_bad_ids(): void
    {
        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [['id' => 'not-a-uuid', 'type' => 'carousel', 'data' => []]]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.id', 'pageBuilderBlocks.0.type']);
    }

    public function test_rules_reject_duplicate_ids(): void
    {
        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [
                ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '<p>a</p>']],
                ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '<p>b</p>']],
            ]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.id', 'pageBuilderBlocks.1.id']);
    }

    public function test_rules_cap_the_block_count_and_paragraph_length(): void
    {
        config()->set('pagebuilder.max_blocks', 1);
        config()->set('pagebuilder.max_html_length', 10);

        Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [
                ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '<p>'.str_repeat('x', 20).'</p>']],
                ['id' => self::B, 'type' => 'paragraph', 'data' => ['html' => '<p>b</p>']],
            ]],
        ])->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks', 'pageBuilderBlocks.0.data.html' => 'max']);
    }

    public function test_an_empty_document_is_valid(): void
    {
        Livewire::test(EditorHost::class)->call('validateAll')->assertHasNoErrors();
    }

    public function test_the_document_output_is_normalized(): void
    {
        $component = Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [
                ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '<p>ok<script>x</script></p>']],
                ['id' => self::B, 'type' => 'paragraph', 'data' => ['html' => '<p></p>']],
            ]],
        ]);

        $document = $component->instance()->getPageBuilderDocumentArray();

        $this->assertCount(1, $document['blocks']);
        $this->assertSame('<p>ok</p>', $document['blocks'][0]['data']['html']);
        $this->assertJson($component->instance()->getPageBuilderDocumentJson());
    }

    public function test_the_editor_context_resolves_previews_with_one_lookup(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, [
            'initial' => ['blocks' => [
                ['id' => self::A, 'type' => 'image', 'data' => ['media_id' => 1]],
                ['id' => self::B, 'type' => 'image', 'data' => ['media_id' => 2]],
                ['id' => self::C, 'type' => 'image', 'data' => ['media_id' => 777]],
            ]],
        ]);

        ArrayMediaResolver::$calls = 0;

        $context = $component->instance()->pageBuilderEditorContext();

        $this->assertSame(1, ArrayMediaResolver::$calls);
        $this->assertSame('/m/1.jpg', $context['previews'][self::A]['url']);
        $this->assertSame('Rathaus', $context['previews'][self::A]['alt']);
        $this->assertNull($context['previews'][self::B]['url']);
        $this->assertNull($context['previews'][self::C]);
        $this->assertTrue($context['alignment']);
    }
}
