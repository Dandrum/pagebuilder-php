# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project Overview

`dandrum/livewire-pagebuilder` is a standalone Laravel composer package (VCS-installed, like
`dandrum/datatable` — not published on Packagist): a Livewire block-based content editor. A
`HasPageBuilder` trait turns any host Livewire component into an editor for one JSON-backed
column on a bound model, storing `{"version": 1, "blocks": [{"id", "type", "data"}]}`. A Blade
component renders that JSON anywhere — in a host app's own views, not only an external frontend.

This repo is the package only: no host Laravel app, no database, no HTTP routes, no `.env`.
Everything is exercised through `composer.json`'s own `require`/`require-dev`.

## Architecture

- **Trait, not a base class** (`src/Concerns/HasPageBuilder.php`) — composes into existing
  Livewire components instead of forcing an inheritance hierarchy. The host implements
  `getPageBuilderModel()` and `uploadPageBuilderImage()`, and calls `initializePageBuilder()`
  from its own `mount()`.
- **Block type registry** (`src/Support/BlockRegistry.php`, a singleton built from
  `config('pagebuilder.blocks')`) — each block type is a class implementing
  `src/Contracts/BlockType.php`. New block types are added via config, not by forking the
  package. The four shipped types live in `src/Blocks/`.
- **Document model** (`src/Support/PageBuilderDocument.php`) — the only place that knows the
  on-disk `{version, blocks}` JSON shape; encodes/decodes it.
- **Two Blade entry points**: `<x-pagebuilder::editor>` (admin-facing editor UI) and
  `<x-pagebuilder::render>` (read-only renderer, backed by `src/View/Components/Render.php`).
  Both dispatch per-block to that block type's `editorView()`/`renderView()`.
- **Service provider** (`src/Providers/PageBuilderServiceProvider.php`) wires the registry, the
  `pagebuilder::` view namespace, the `pagebuilder::` Blade component namespace, and publishable
  config/views/JSON translations — the same pattern as the sibling `dandrum/datatable` package's
  `DataTableServiceProvider`.

See `.claude/rules/architecture.md` for the full block lifecycle (add/duplicate/remove/reorder/
upload/validate/save) and how to add a block type. See `.claude/rules/laravel.md` for code style
and the Blade/Alpine/Trix/SortableJS conventions used in the views. See `.claude/rules/testing.md`
for the current (pre-test-suite) state of testing this package.

## Development Setup

Plain PHP + Composer on the host machine — no Docker, no app to boot, no services to start.

```sh
composer install
```

## Common Commands

```sh
# Format (Pint, laravel preset)
vendor/bin/pint

# Check only, no changes — mirrors what CI should run
vendor/bin/pint --test

# Syntax-check every PHP file (no test suite exists yet — see testing.md)
for f in $(find src config -name "*.php"); do php -l "$f"; done
```

## Coding Conventions

- PSR-4 root namespace `Dandrum\PageBuilder\`.
- Laravel Pint, `laravel` preset (`pint.json`) — run before committing; same ruleset as the
  sibling `dandrum/datatable` package.
- `declare(strict_types=1)` in every file under `src/`.
- Block classes, the registry, and the document are `final`.
- **User-facing strings always go through `__('...')`**, keyed by the literal English sentence
  (not a dotted key), with an entry in both `resources/lang/en.json` and `resources/lang/de.json`
  — mirrors the translation convention of this package's primary consumer, `dandrum/DotaLan`.
- No sidequests: a change to one block type or view should not ripple into unrelated block types
  unless the task actually requires it.
- The host app is expected to already load **Alpine.js**, **Trix**, and **SortableJS** in its own
  asset bundle — this package ships no JS/CSS build step of its own and must not grow one just to
  avoid that requirement.

## Package Consumers

No host app lives in this repo. The only known consumer is `dandrum/DotaLan`
(`backend_new/`) — not yet wired in; integrating this package into `Article`/`Page` there is a
separate, later change (GitLab issue #166 covers the package build only). Before changing the
JSON schema, the trait's public method signatures, or the shape of `config/pagebuilder.php`,
check that consumer rather than assuming nothing depends on current behavior.
