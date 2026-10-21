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

These are **host-provided globals**, not bundled — the package has no `package.json`, no Vite
config, no build step. Blade views assume `Alpine` and `trix-editor` (custom element) already exist
on `window` because the host app's own asset bundle loaded them (see `dandrum/DotaLan`'s
`resources/js/app.js` for the reference setup: `import 'trix'`, `import 'alpinejs'`). Do not add an
asset pipeline to this package to work around that — it would duplicate what every realistic host
already has.

- Rich text blocks (`paragraph`, `image_text`) use the same `x-data` + `@entangle` + `wire:ignore`
  + `trix-change` pattern as `dandrum/DotaLan`'s own `resources/views/components/form-fields/
  textarea.blade.php` — keep new rich-text-ish blocks consistent with that pattern rather than
  inventing a different one.
- Reordering (`<x-pagebuilder::editor>`) is plain Livewire round-trips — move-up/move-down icon
  buttons call `movePageBuilderBlockUp()`/`movePageBuilderBlockDown()` directly, no Alpine/JS state
  of its own and no drag library. This was a deliberate choice over SortableJS: a drag handle that
  also has to host clickable action buttons (duplicate/delete) is a real interaction conflict
  (drag-start hijacking a button tap), and arrow buttons sidestep it entirely. `Sortable` is **not**
  a host requirement of this package — don't reintroduce that assumption without re-adding the
  actual drag wiring.

## Anonymous Blade components

`resources/views/components/editor.blade.php`, `resources/views/components/insert-row.blade.php`,
and every `resources/views/blocks/edit|render/*` view are **anonymous** components/partials
(`@props(...)` + plain Blade, no PHP class) — add a class-based component (like
`src/View/Components/Render.php`) only when a view genuinely needs constructor logic (as `Render`
does, to decode the JSON before rendering). Don't promote a simple partial to a class component "for
consistency" — the package deliberately keeps that to one class.
