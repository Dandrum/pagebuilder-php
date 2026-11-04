# Laravel & Livewire Conventions

## Style

- Laravel Pint (`pint.json`), `laravel` preset, same three overrides as `dandrum/datatable`
  (`single_import_per_statement`, `no_unused_imports`, `ordered_traits` all disabled) — run
  `vendor/bin/pint` before committing, `vendor/bin/pint --test` to check only.
- `declare(strict_types=1)` at the top of every file under `src/`.
- Prefer `final` for block classes, the registry, and the document value object — this package
  has no inheritance-based extension point; extension is via the `BlockType` interface + config,
  not subclassing package internals.

## View namespace & component namespace

- Views are registered under the `pagebuilder::` namespace (`loadViewsFrom`), components under the
  `pagebuilder::` Blade component namespace (`Blade::componentNamespace(...)`) — both point at
  `resources/views`. A view string returned from `BlockType::editorView()`/`renderView()` must be a
  `pagebuilder::...` path, not a bare relative path, or `@include()` resolves against the host
  app's own view paths instead of the package's.
- `<x-pagebuilder::editor>` and `<x-pagebuilder::render>` are the only two public Blade entry
  points. Everything under `resources/views/blocks/` is an implementation detail reached only via
  `@include($blockType->editorView()/renderView())` — don't reference those paths directly from a
  consuming app's Blade code, go through the block type's view methods so a future rename doesn't
  break consumers.

## Translations

- JSON-style (`resources/lang/en.json`/`de.json`), **not** `lang/en/pagebuilder.php` with dotted
  keys — loaded via `loadJsonTranslationsFrom()`, keyed by the literal English UI string (e.g.
  `__('Move up')`), matching how the primary consumer app (`dandrum/DotaLan`) does its own
  translations. A package-level override path is also loaded
  (`$this->app->langPath('vendor/pagebuilder')`) so a host app can publish
  (`pagebuilder-translations` tag) and override strings without forking.
- Every new UI string needs both JSON files updated in the same change — search both before adding
  a key, this package is small enough that duplicate near-identical keys are easy to introduce by
  accident.

## Alpine / Trix integration

Alpine comes with Livewire; **Trix** is host-provided (`import 'trix'` + `trix/dist/trix.css`), the
package has no `package.json` or build step. The package does ship plain CSS files
(`resources/css/pagebuilder-editor.css`, `pagebuilder-content.css`) that the host imports.

- Rich text (`components/rich-text.blade.php`, used by `paragraph` and `image-text`): one
  `<trix-toolbar>` per block with only bold, italic, link and bullet/number list, attachments blocked
  (`trix-file-accept.prevent`). **No `@entangle`**: after a reorder an index-bound Alpine component
  would write into another block. Instead every `trix-change` (debounced 250 ms) looks the block up by
  id (`$wire.pageBuilderBlocks.findIndex(b => b.id === id)`) and does a deferred
  `$wire.$set('pageBuilderBlocks.'+i+'.data.html', trix.value, false)`; `trix-initialize` loads the HTML
  by the same lookup. Use `trix.value` (the serialized document), never `trix.innerHTML`.
- Reordering uses Livewire 4 `wire:sort` with a dedicated grip (`wire:sort:handle`) - see
  `architecture.md`. `Sortable` is not a host requirement; Livewire bundles it.

## CSS conventions

Package views carry no Tailwind classes, only `pb-*` classes styled by the shipped CSS. Selectors are
single-class (`:where()` inside `.pb-prose`) for low specificity, not in a cascade layer. Colors and
spacing are `--pb-*` custom properties declared on `.pagebuilder` / `.pb-content`; hosts override them
after importing the CSS. Don't add `@apply` or utility classes to the package views.
## Anonymous Blade components

`resources/views/components/editor.blade.php`, `resources/views/components/insert-row.blade.php`,
and every `resources/views/blocks/edit|render/*` view are **anonymous** components/partials
(`@props(...)` + plain Blade, no PHP class) — add a class-based component (like
`src/View/Components/Render.php`) only when a view genuinely needs constructor logic (as `Render`
does, to decode the JSON before rendering). Don't promote a simple partial to a class component "for
consistency" — the package deliberately keeps that to one class.
