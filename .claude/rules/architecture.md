# Architecture

## Data shape

A bound model's JSON column holds:

```json
{
    "version": 1,
    "blocks": [
        { "id": "uuid", "type": "heading", "data": { "level": 2, "text": "…", "align": "left" } }
    ]
}
```

`src/Support/PageBuilderDocument.php` is the single place that encodes/decodes this. `version`
exists for future schema migrations (none implemented yet — there is currently exactly one
version and nothing reads `version` to branch behavior). Every block has a stable `id` (a UUID,
assigned once when the block is added) so the frontend/editor can track identity across reorders
without relying on array position.

## Trait lifecycle (`src/Concerns/HasPageBuilder.php`)

The trait has no constructor/mount hook of its own — Livewire does not guarantee trait `mount*`
hooks run after the host's own `mount()` has set up the bound model property, so initialization is
explicit instead of automatic:

1. Host's `mount()` assigns the bound model to its own property, then calls
   `$this->initializePageBuilder()`.
2. `initializePageBuilder()` reads `getPageBuilderStorageField()` (default `'content'`) off
   `getPageBuilderModel()` via `data_get()`, decodes it through `PageBuilderDocument` (accepts
   either a JSON string or an already-cast array — works whether or not the host model casts the
   column to `array`/`json`), and seeds `$this->pageBuilderBlocks`.
3. The host's Blade view renders `<x-pagebuilder::editor :blocks="$pageBuilderBlocks"
   :block-types="$this->pageBuilderBlockTypes()" />`.
4. User interactions call trait methods directly via `wire:click`/`wire:model` (same Livewire
   component instance — the editor is a plain Blade component included in the host's own view, not
   a nested Livewire component, so there is no cross-component data round-trip).
5. On submit, host validates (`pageBuilderRules()` merged into its own rules, or `validatePageBuilderBlocks()`) then persists
   `$this->getPageBuilderDocumentJson()` (JSON string) or `getPageBuilderDocumentArray()` (plain
   array, for a model casting the column to `array`/`json`) onto its own model and saves it.

## Block lifecycle

- **Insert** (`insertPageBuilderBlock(string $type, string $afterBlockId = '')`): looks up the type
  in the registry, builds a new block with a fresh UUID and `defaultData()`, and splices it in
  right after `$afterBlockId` — or at the very start of the list when `$afterBlockId` is `''` (the
  default) or no longer matches any block. The editor renders one of these insert points *between
  every pair of blocks* (and one before the first block), each offering every registered block type
  as a small "+ Label" button — not a single "add block" menu at the end. `addPageBuilderBlock(string
  $type)` still exists as a plain "append at the end" convenience wrapper around this (it resolves
  the current last block's id and calls `insertPageBuilderBlock` with it).
- **Duplicate** (`duplicatePageBuilderBlock(string $blockId)`): deep-copies the block array,
  assigns a new UUID, splices it immediately after the original.
- **Remove** (`removePageBuilderBlock(string $blockId)`): before removing, if the block's `data`
  has a `url` or `imageUrl`, calls `deletePageBuilderImage()` (no-op by default — override in the
  host to actually clean up storage, e.g. S3).
- **Reorder**: three paths, all by block id. Drag & drop is Livewire 4's `wire:sort` on the list
  in `editor.blade.php`: each `wire:sort:item` wraps the block *and its trailing insert row* (the
  leading insert row sits outside the sortable container, so Sortable's `newIndex` equals the block
  index) and `sortPageBuilderBlock(string $blockId, int $position)` moves it (position clamped, unknown
  id ignored). Only the dedicated grip button carries `wire:sort:handle`, so the action buttons and the
  Trix area (`wire:sort:ignore`) never start a drag. `movePageBuilderBlockUp/Down` remain as the
  keyboard / non-drag alternative (disabled at the edges). `reorderPageBuilderBlocks(array $ids)` is a
  public API the editor does not call.
- **Pick** (`#[On('pagebuilder-image-picked')] pickPageBuilderImage($blockId, $mediaId)`): the host's
  picker answers the editor's `pagebuilder-pick-image` event. Unknown block ids are ignored; the media id
  is validated with `pageBuilderMediaIdRules()`; failure -> error on `...data.media_id`, nothing
  assigned. Success sets `media_id`, clears alt/caption/credit and drops a pending upload.
  Pick, upload, clear and the image validation rules apply to every block implementing
  `Contracts\ImageBearingBlock` (`image`, `image_text`); `imageUrlKey()` (`url` / `imageUrl`) names the URL key. A
  deferred upload stored by `storePendingPageBuilderUploads()` only resets the image keys, so an image + text
  block keeps its html/position/align.
- **Image upload** (`updatedPageBuilderUploads($value, $key)`): `$pageBuilderUploads` is an **array**
  property (`pageBuilderUploads.{blockId}`), one entry per block - don't collapse it to a single
  scalar. The hook validates the file with `pageBuilderUploadRules()` first (invalid files are
  dropped), ignores everything when `image.upload = off` or the key is no image block, and then
  either stores right away (`immediate`, via `storePageBuilderUpload()`) or keeps the file
  (`deferred`) until the host calls `storePendingPageBuilderUploads()` after validation/authorization.
- **Validate**: `pageBuilderRules()` / `pageBuilderMessages()` build the rules without validating
  (structure: block count, uuid ids, registered type; per-block `rules()`; image rules that depend on
  the source and on pending uploads) so the host can merge them into its own `validate()`.
  `validatePageBuilderBlocks()` is the stand-alone convenience.
- **Normalize**: `PageBuilderDocument::normalized()` (applied by `getPageBuilderDocumentArray/Json()`)
  drops malformed / unregistered / duplicate-id blocks and lets each `ContentAwareBlock::normalize()`
  whitelist keys, sanitize HTML (`Contracts\HtmlSanitizer`, default `PurifierHtmlSanitizer`) or return
  null to drop the block. Idempotent. `mediaIds()` / `plainText()` read a document without rendering.

## Contracts

`BlockType` (required), `ContentAwareBlock` (optional: `normalize`, `plainText`), `HtmlSanitizer`,
`ImageMediaResolver` (`findMany`, `previewUrl`, `metadata`; bound from `pagebuilder.image.resolver`).
`<x-pagebuilder::render>` resolves all `media_id`s of a document with one `findMany()` call.

## Adding a new block type

1. Create a class in `src/Blocks/` (or anywhere in the host app) implementing
   `src/Contracts/BlockType.php`.
2. Add two Blade views: one for `editorView()` (receives `block`, `index` — used for
   `wire:model="pageBuilderBlocks.{{ $index }}.data.…"` bindings) and one for `renderView()`
   (receives `data`, `block` — read-only, no Livewire bindings, must work standalone since
   `<x-pagebuilder::render>` can run outside any Livewire component).
3. Register the class in `config/pagebuilder.php`'s `blocks` array (publish the config first if
   doing this from a consuming app rather than this package itself).
4. `BlockRegistry` resolves it by `key()` from then on; both `<x-pagebuilder::editor>` and
   `<x-pagebuilder::render>` skip (not error on) any stored block whose `type` isn't currently
   registered, so removing/renaming a block type is forward-compatible with already-stored
   documents, it just stops rendering those blocks until the type is re-registered.

## Known limitation: autosave/draft and undo/redo

Not implemented. The trait only holds one in-memory `$pageBuilderBlocks` array per request/session
— there is no separate draft column, no history stack. If a host app needs either, it's additive
on top of the trait (e.g. a second `content_draft` column read/written the same way via a second
`PageBuilderDocument`), not a change to the trait's core save flow.
