---
name: project-explorer
description: This skill should be used when the user asks "where should this go", "where is X handled", "which file do I edit for Y", "how do I add a block type", or when starting any non-trivial task in this package and it isn't yet clear which file owns a given behavior (block definition, editor UI, renderer, trait/service provider wiring). Provides a concrete lookup table and decision tree grounded in this repo's actual (small) layout.
version: 1.0.0
---

# Project Explorer (livewire-pagebuilder)

## Overview

This package is small enough that most things have exactly one home — the hard part is usually
knowing which of the *two* places a given piece of block-related logic belongs: the **block type
class** (data shape, validation, which views to use) vs. the **view** (markup/interaction for that
block). This skill is the lookup table for that, plus the handful of cross-cutting files.

Don't guess a file's existence from a block's name alone — verify with Glob/Grep, especially for
the `image_text` block, whose key (`image_text`, snake_case) doesn't match its class name
(`ImageTextBlock`) or its view folder segment (`image-text`, kebab-case).

## Locating everything for one block type

Given a block key (e.g. `heading`, `image_text`), check each layer explicitly:

| Layer | Path pattern | Example (`heading`) | Example (`image_text`) |
|---|---|---|---|
| Block class | `src/Blocks/{PascalCase}Block.php` | `src/Blocks/HeadingBlock.php` | `src/Blocks/ImageTextBlock.php` |
| Editor view | `resources/views/blocks/edit/{kebab-case}.blade.php` | `blocks/edit/heading.blade.php` | `blocks/edit/image-text.blade.php` |
| Render view | `resources/views/blocks/render/{kebab-case}.blade.php` | `blocks/render/heading.blade.php` | `blocks/render/image-text.blade.php` |
| Registered? | `config/pagebuilder.php` `blocks` array | — | — |
| Translation strings it uses | `resources/lang/en.json` + `de.json` | `"Heading"`, `"Heading text"`, align labels | `"Image + Text"`, `"Image position"`, align labels |

Fast one-shot sweep for a block key or class name:
```sh
grep -rln "ImageTextBlock\|image_text\|image-text" src resources config
```

## Decision tree: where does this change go?

- **New block type**: see `.claude/rules/architecture.md` → "Adding a new block type" — new
  class in `src/Blocks/`, two new Blade views, one new line in `config/pagebuilder.php`. Don't put
  block-specific markup inside `resources/views/components/editor.blade.php` — that file only
  knows how to loop blocks and dispatch to `editorView()`/`renderView()`, it has no per-type logic.
- **Change what data a block stores / how it validates**: `src/Blocks/{Name}Block.php`'s
  `defaultData()` and `rules()` — not the trait, not the views (views just bind to whatever keys
  `defaultData()`/`rules()` declare).
- **Change the add/duplicate/remove/reorder/upload/validate behavior shared by all blocks**:
  `src/Concerns/HasPageBuilder.php` — this is the one file every block type and both Blade entry
  points depend on; changes here are cross-cutting, see `.claude/rules/architecture.md` for the
  full lifecycle before touching it.
- **Change the JSON on-disk shape** (`{version, blocks}`, or what a block array looks like):
  `src/Support/PageBuilderDocument.php` — and check `README.md`'s schema example and
  `CLAUDE.md`'s "Package Consumers" note, since this is the one thing an external consumer could
  already depend on.
- **Change what's registered/how blocks are looked up by key**: `src/Support/BlockRegistry.php`
  (the lookup mechanism) vs. `config/pagebuilder.php` (which classes are registered) — most changes
  belong in the config, not the registry class itself.
- **Change publishable config/views/translations, or the `pagebuilder::` view/component
  namespace**: `src/Providers/PageBuilderServiceProvider.php`.
- **Change the public editor/renderer markup shared by all block types** (toolbar, drag handles,
  duplicate/delete buttons, the render loop): `resources/views/components/editor.blade.php` and
  `resources/views/render.blade.php` respectively.
- **Any new user-facing string**: goes through `__('...')`, keyed by the literal English sentence,
  added to both `resources/lang/en.json` and `resources/lang/de.json` — search both before adding
  what might already exist under slightly different wording.
- **Add a test**: see `.claude/rules/testing.md` first — there is no test suite yet, so this is
  "set one up" (Testbench), not "add a test file to an existing suite".

## Naming exceptions (verified — don't assume the pattern holds)

- Block **key** strings are snake_case where needed for the on-disk JSON (`image_text`), block
  **class** names are PascalCase (`ImageTextBlock`), and their **view folder** segments are
  kebab-case (`image-text.blade.php`) — three different cases for the same concept. Always check
  `BlockType::key()` in the class itself rather than assuming it matches the filename.
- `src/View/Components/Render.php` is the only class-based Blade component in the package — every
  other `resources/views/**/*.blade.php` file is an anonymous `@props(...)` partial. Don't look for
  a `src/View/Components/Editor.php`; it doesn't exist, `<x-pagebuilder::editor>` is anonymous.
- `src/Concerns/HasPageBuilder.php` internally composes Livewire's own `WithFileUploads` trait
  (`use WithFileUploads;` inside the trait) — file-upload-related methods a consumer sees on their
  component (e.g. anything `WithFileUploads` provides) aren't defined in this package's source at
  all; check Livewire's own source/docs for those, not this repo.
