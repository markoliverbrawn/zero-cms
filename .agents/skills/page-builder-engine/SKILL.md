---
name: page-builder-engine
description: Explains Zero CMS's dynamic layout page-builder block engine — how blocks are stored as JSON, pre-rendered for the admin inserter, and serialized generically by class-based JS conventions. Use when modifying block_builder.php, block_builder.js, adding a new block type, or debugging why a block isn't appearing/saving correctly in the admin editor.
---

# Dynamic Layout Page Builder Engine

Pages are stored in the database under a text field named `content` containing serialized JSON payloads of multiple visual blocks:

```json
[
  {"type": "text", "title": "Welcome", "content": "<p>Raw HTML</p>"},
  {"type": "accordion", "title": "FAQs", "items": [{"title": "Question?", "content": "Answer"}]}
]
```

## 1. Server-Side Pre-Rendering (`src/Modules/Admin/Views/block_builder.php`)

* The page builder retrieves all dynamically registered block configs from `App::getRegisteredBlocks()`.
* For each block type, it mocks an empty context block and buffers its admin template view (dynamically resolved from the block's `'admin_view'` config option, falling back to `src/Modules/Admin/Views/blocks/{type}.php`) into an output buffer to compile a dictionary:
  ```php
  const REGISTERED_BLOCK_TEMPLATES = <?php echo json_encode($preRenderedTemplates); ?>;
  ```

## 2. Client-Side Instant Insertion

When an editor opens the sliding block inserter modal drawer and adds a block, JavaScript instantly fetches its raw HTML fields layout from `REGISTERED_BLOCK_TEMPLATES[type]` and appends it to the DOM — ensuring zero latency and absolute modular decoupling.

## 3. Convention-Based Generic JS Serializer

Instead of utilizing separate JavaScript code blocks to parse and serialize different fields, a single, fully generic JavaScript function compiles the page state upon save by analyzing DOM class structures:

* Values of elements matching `.block-title-input` are assigned to `blockData.title`.
* Content of editors matching `.editor-area` are assigned to `blockData.content`.
* Inputs with classes matching `.block-{field}-input` (e.g. `.block-image_path-input`) are automatically parsed into `blockData.{field}`.
* Nested child item rows matching `.{type}-item-row` (e.g. `.accordion-item-row`) are scanned, automatically collecting child fields into `blockData.items = [...]`.

## Related conventions

Modules register their own block types dynamically on bootstrap (see the "Loose Coupling & Modular Registration" convention in `GEMINI.md`) — core `App`/`Router`/`block_builder.php` never hardcode a specific block type. Block content-extraction helper classes (implementing `BlockHelperInterface`) live under `src/Support/Blocks/` and must remain 100% database-query-free, operating purely on the passed-in JSON block data (see Rule 18's "Database-Free OOP Block Helpers" mandate).
