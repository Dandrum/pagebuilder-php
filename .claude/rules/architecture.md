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
5. On submit, host calls `$this->validatePageBuilderBlocks()` then persists
   `$this->getPageBuilderDocumentJson()` (JSON string) or `getPageBuilderDocumentArray()` (plain
   array, for a model casting the column to `array`/`json`) onto its own model and saves it.

## Block lifecycle

- **Add** (`addPageBuilderBlock(string $type)`): looks up the type in the registry, appends a new
  block with a fresh UUID and `defaultData()`.
- **Duplicate** (`duplicatePageBuilderBlock(string $blockId)`): deep-copies the block array,
  assigns a new UUID, splices it immediately after the original.
- **Remove** (`removePageBuilderBlock(string $blockId)`): before removing, if the block's `data`
  has a `url` or `imageUrl`, calls `deletePageBuilderImage()` (no-op by default — override in the
  host to actually clean up storage, e.g. S3).
- **Reorder** (`reorderPageBuilderBlocks(array $orderedIds)`): called from the editor's SortableJS
  `onEnd` handler with the DOM's new id order; re-keys `$pageBuilderBlocks` by that order via a
  `collect()->keyBy('id')` lookup — unknown ids are silently dropped, not errored.
- **Image upload** (`updatedPageBuilderUploads($value, $key)`): `$pageBuilderUploads` is an
  **array** property (`pageBuilderUploads.{blockId}`), not a single scalar — this is deliberate so
  multiple image/image_text blocks can each have their own `<input type="file"
  wire:model="pageBuilderUploads.{{ $block['id'] }}">` without a shared "which block is this
  upload for" tracking property and the request-ordering race that would create. Livewire invokes
  `updated{Property}($value, $key)` with `$key` as the nested path remainder (`{blockId}`), which
  is how the hook knows which block to attach the uploaded URL to. Don't collapse this back to a
  single shared upload property.
- **Validate** (`validatePageBuilderBlocks()`): builds a `pageBuilderBlocks.{i}.data.{field}` rule
  set per block from that block type's `rules()` and calls the host's own `$this->validate()` —
  errors surface as normal Livewire validation errors, `@error('pageBuilderBlocks.0.data.alt')`
  works in the editor's block-edit views.

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
