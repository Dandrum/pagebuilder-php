<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Concerns;

use Dandrum\PageBuilder\Support\BlockRegistry;
use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Turns any Livewire component into a block-based content editor for one
 * JSON-backed column of a bound model. The host component must:
 *
 * - call `$this->initializePageBuilder()` from its own `mount()`, after the
 *   bound model has been assigned to a property;
 * - implement `getPageBuilderModel()`, returning that bound model;
 * - implement `uploadPageBuilderImage()`, storing the file however the host
 *   app wants (S3, local disk, …) and returning the public URL/path.
 *
 * Overriding `getPageBuilderStorageField()` and `deletePageBuilderImage()` is
 * optional.
 */
trait HasPageBuilder
{
    use WithFileUploads;

    /** @var array<int, array{id: string, type: string, data: array<string, mixed>}> */
    public array $pageBuilderBlocks = [];

    /** @var array<string, TemporaryUploadedFile> keyed by block id */
    public array $pageBuilderUploads = [];

    public function initializePageBuilder(): void
    {
        $raw = data_get($this->getPageBuilderModel(), $this->getPageBuilderStorageField());

        $document = is_array($raw)
            ? PageBuilderDocument::fromArray($raw)
            : PageBuilderDocument::fromJson($raw);

        $this->pageBuilderBlocks = $document->blocks;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function pageBuilderBlockTypes(): array
    {
        return collect($this->pageBuilderRegistry()->all())
            ->map(fn ($block) => ['key' => $block::key(), 'label' => $block->label()])
            ->values()
            ->all();
    }

    public function addPageBuilderBlock(string $type): void
    {
        $lastBlock = collect($this->pageBuilderBlocks)->last();

        $this->insertPageBuilderBlock($type, $lastBlock['id'] ?? '');
    }

    /**
     * Inserts a new block of `$type` right after the block identified by
     * `$afterBlockId` — or at the very start of the list when `$afterBlockId`
     * is `''` (or no longer matches any block).
     */
    public function insertPageBuilderBlock(string $type, string $afterBlockId = ''): void
    {
        $blockType = $this->pageBuilderRegistry()->get($type);

        $newBlock = [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $blockType->defaultData(),
        ];

        $index = $afterBlockId === '' ? null : $this->findPageBuilderBlockIndex($afterBlockId);

        if ($index === null) {
            array_unshift($this->pageBuilderBlocks, $newBlock);
        } else {
            array_splice($this->pageBuilderBlocks, $index + 1, 0, [$newBlock]);
        }
    }

    public function movePageBuilderBlockUp(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null || $index === 0) {
            return;
        }

        $this->swapPageBuilderBlocks($index, $index - 1);
    }

    public function movePageBuilderBlockDown(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null || $index === array_key_last($this->pageBuilderBlocks)) {
            return;
        }

        $this->swapPageBuilderBlocks($index, $index + 1);
    }

    public function duplicatePageBuilderBlock(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        $copy = $this->pageBuilderBlocks[$index];
        $copy['id'] = (string) Str::uuid();

        array_splice($this->pageBuilderBlocks, $index + 1, 0, [$copy]);
    }

    public function removePageBuilderBlock(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        $data = $this->pageBuilderBlocks[$index]['data'] ?? [];
        $imageUrl = $data['url'] ?? $data['imageUrl'] ?? null;

        if (filled($imageUrl)) {
            $this->deletePageBuilderImage($imageUrl);
        }

        unset($this->pageBuilderBlocks[$index]);
        $this->pageBuilderBlocks = array_values($this->pageBuilderBlocks);
    }

    /**
     * Not called by the shipped `<x-pagebuilder::editor>` (which reorders via
     * `movePageBuilderBlockUp()`/`movePageBuilderBlockDown()` instead), but kept as a public API
     * for a host that wires up its own drag-and-drop reordering.
     *
     * @param  array<int, string>  $orderedIds
     */
    public function reorderPageBuilderBlocks(array $orderedIds): void
    {
        $blocksById = collect($this->pageBuilderBlocks)->keyBy('id');

        $this->pageBuilderBlocks = collect($orderedIds)
            ->map(fn (string $id) => $blocksById->get($id))
            ->filter()
            ->values()
            ->all();
    }

    public function updatedPageBuilderUploads(mixed $value, string $key): void
    {
        if (! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $this->uploadPageBuilderBlockImage($key, $value);

        unset($this->pageBuilderUploads[$key]);
    }

    public function uploadPageBuilderBlockImage(string $blockId, TemporaryUploadedFile $file): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        $url = $this->uploadPageBuilderImage($file);
        $field = $this->pageBuilderBlocks[$index]['type'] === 'image_text' ? 'imageUrl' : 'url';

        $this->pageBuilderBlocks[$index]['data'][$field] = $url;
    }

    /**
     * Validates every block's `data` against its block type's `rules()`.
     * Call this from the host component's own submit/save method before
     * persisting `getPageBuilderDocumentJson()`/`getPageBuilderDocumentArray()`.
     */
    public function validatePageBuilderBlocks(): void
    {
        $registry = $this->pageBuilderRegistry();
        $rules = [];

        foreach ($this->pageBuilderBlocks as $i => $block) {
            if (! $registry->has($block['type'])) {
                continue;
            }

            foreach ($registry->get($block['type'])->rules() as $field => $fieldRules) {
                $rules["pageBuilderBlocks.{$i}.data.{$field}"] = $fieldRules;
            }
        }

        // No blocks (or none needing validation) is a legitimate state — e.g. a brand-new
        // document, or a locale nobody has added content to yet. Livewire's validate() throws
        // MissingRulesException on *any* empty rules array, treating it as a forgotten rules()
        // method rather than "nothing to check here", so this must short-circuit instead.
        if ($rules === []) {
            return;
        }

        $this->validate($rules);
    }

    /**
     * @return array{version: int, blocks: array<int, array<string, mixed>>}
     */
    public function getPageBuilderDocumentArray(): array
    {
        return (new PageBuilderDocument(1, $this->pageBuilderBlocks))->toArray();
    }

    public function getPageBuilderDocumentJson(): string
    {
        return (new PageBuilderDocument(1, $this->pageBuilderBlocks))->toJson();
    }

    protected function getPageBuilderStorageField(): string
    {
        return 'content';
    }

    public function deletePageBuilderImage(string $url): void
    {
        // No-op by default — override to clean up the file in the host app's storage.
    }

    abstract protected function getPageBuilderModel(): Model;

    abstract public function uploadPageBuilderImage(TemporaryUploadedFile $file): string;

    private function pageBuilderRegistry(): BlockRegistry
    {
        return app(BlockRegistry::class);
    }

    private function findPageBuilderBlockIndex(string $blockId): ?int
    {
        foreach ($this->pageBuilderBlocks as $i => $block) {
            if ($block['id'] === $blockId) {
                return $i;
            }
        }

        return null;
    }

    private function swapPageBuilderBlocks(int $a, int $b): void
    {
        [$this->pageBuilderBlocks[$a], $this->pageBuilderBlocks[$b]] =
            [$this->pageBuilderBlocks[$b], $this->pageBuilderBlocks[$a]];
    }
}
