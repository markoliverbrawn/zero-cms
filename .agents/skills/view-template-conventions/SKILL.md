---
name: view-template-conventions
description: Enforces Zero CMS's conventions for creating or editing view/template files — theme views under src/Views/themes/*, admin views under src/Modules/*/Views/, and block admin/preview partials. Use when writing or scaffolding any view template (.php file rendered via Template::renderFile/App::render), especially around inline styles, scripts, escaping, and back-office table markup.
---

# View Template Creation Conventions

View templates are still plain `.php` files — everything in the `php-file-conventions` skill applies to them too (namespace `use` imports, no error suppression, no emojis, etc.). This skill covers the rules that are specific to *templates as rendered output*, which is a distinct category from classes/controllers/models.

## How views actually execute (read this before assuming an escaping/data layer exists)

* `Template::renderFile($path, $data)` is a thin wrapper around `extract($data, EXTR_SKIP)` followed by a plain `include`. **There is no templating engine and no auto-escaping.** Every piece of dynamic output in a view is raw PHP interpolation unless you escape it yourself.
* `App::render($view, $data)` resolves the file two different ways depending on the view name:
  - If `$view` starts with a registered prefix (e.g. `admin/...`, via `App::registerViewDir()`), it resolves to that module's view directory, and pairs it with a `layout.php` in the *same* directory.
  - Otherwise it's a frontend view: resolved from the active site's `theme` under `src/Views/themes/{theme}/{view}.php`, falling back to `src/Views/themes/default/{view}.php` if the current theme doesn't have that file (theme fallback also applies per-file to `layout.php` itself — a theme can override just one view and still inherit the rest from `default`).
  - Either way, the resolved view's output becomes `$data['content']`, which is then rendered inside the matching `layout.php`.
* `$data['csrf']`, `$data['error']`, and `$data['session']` are auto-injected into every render — you don't need to pass them yourself, and you shouldn't clobber them.

## Escaping (Rule 28) — mandatory, not optional

Because there's no auto-escaping layer, **every** dynamic value echoed into HTML must go through `Zero\Support\Str::escape()`:

```php
<?= Str::escape($page->title) ?>
```

Raw `htmlspecialchars()` must never be called directly from a view. Declare `use Zero\Support\Str;` at the top (Rule 13 applies to views too).

## No inline styles (Rule 1)

Zero inline `style="..."` attributes, with exactly one exception: setting a CSS custom property/variable dynamically —

```php
<!-- OK -->
<div style="--cols-desktop: <?= (int)$columns ?>;">

<!-- NOT OK -->
<div style="margin-top: 20px;">
```

All layout/spacing/color rules belong in external stylesheets under `assets/css/` (admin-specific styles in `assets/css/admin/*.css`, imported via `assets/css/admin.css`). Use existing utility classes (`.flex-1`, `.width-auto`, `.block-flex-row`, etc.) instead of ad-hoc inline declarations. (The stylesheets themselves must use native CSS nesting and never `!important` — that's enforced in the CSS files, not in the view PHP, so it's out of scope here, but don't route around it by pushing styles inline into the view.)

## No inline `<script>` tags (Rule 17)

Zero raw `<script>...</script>` blocks containing executable JS inside any view. Interactive behavior (carousels, accordions, click delegation) lives in dedicated files under `/assets/js/blocks/` (e.g. `accordion.js`), loaded centrally at the bottom of the theme's `layout.php` — never inlined per-view.

## No emojis (Rule 12)

Anywhere in rendered output — admin dashboards, public views, everything. Use inline SVGs or styled typography instead.

## Full-width layouts (Rule 16)

Administrative, back-office, and public cascading layouts must be full-width. Never introduce `max-width` limits on outer wrapper elements — use `width: 100%; max-width: none; margin: 0;` on top-level wrapper classes.

## Back-office table listings (Rule 19)

Every back-office/admin table view wraps its table in `.listrecords` and uses plain, unstyled `<table>` markup — no frontend-specific table styling (`.threads-table`, `.forum-container`, etc.) inside admin panels. Include `data-label` attributes on `<td>` cells for responsive collapsing. See `src/Modules/Admin/Views/model/list.php` for the reference implementation.

## No N+1 queries in list/loop templates (Rule 18)

This is explicitly a templates rule, not just a controller rule:
* Never call a model finder or a `::count()`/relational lookup *inside* a view's loop over rows.
* Any per-row relational metadata (e.g. a cascade-delete count) must be lazy-loaded on demand via AJAX when the user triggers an action — not computed eagerly for every row on page load.
* The calling controller/template must pre-batch: parse the raw data once, collect every needed ID, run exactly one query to build an in-memory lookup map, and pass that map into the view — the view only reads from the map, it never queries.

## UTC storage, localized display (Rule 22)

Views are where timestamp localization happens — and *only* views. Never localize a timestamp anywhere else. Use `I18n::localizeDateTime($utcDateTimeString)` in the view/display layer to convert a stored UTC value for display; it falls back to the active `Site`'s `timezone` setting if the logged-in user has no timezone preference configured. Database writes elsewhere in the codebase always use `gmdate('Y-m-d H:i:s')` — a view should never see or write a non-UTC timestamp, only display one.

## Dynamic help text, not hardcoded strings (Rule 6)

Admin form inputs never hardcode a description/explanation string in the view. Helper text resolves dynamically: check localized dictionaries for `{field}_help`/`{field}_desc` first, then fall back to a model-defined `helper_text`/`description`, so the same view stays correct across every supported locale (English, Spanish, Croatian, Māori) without per-language view forks.

## Block preview / admin-view partials

* **Sanitizer-safe interactivity (Rule 4):** the block builder preview iframe strips `<button>` and `<script>` elements. Interactive elements inside a block's admin/preview template must be generic `div`/wrapper elements, with the click/toggle behavior bound via parent-to-iframe event delegation in the parent JS — not inline handlers or real buttons.
* **No leaking sensitive block config (Rule 10):** a block's admin/preview view must only ever transmit a stateless `block_id` on submission, via the `.block-id-input` hidden field convention — never render a sensitive config value (like a recipient email) directly into the markup, even obfuscated.
* **Generic JS serializer conventions:** field inputs must follow the `.block-title-input`, `.editor-area`, `.block-{field}-input`, and `.{type}-item-row` class-naming conventions so the single generic client-side serializer can pick them up automatically — see the `page-builder-engine` skill for the full mechanism. A module registers its own block admin view via `'admin_view'` in its `App::registerBlock()` config; it does not get centralized into `src/Modules/Admin/Views/blocks/`.

## Destructive actions use the modal, not `confirm()`/`alert()` (Rule 21)

Any deletion or destructive action triggered from a view must go through `window.adminConfirm(options)` (wired to `#admin-confirm-modal` in `layout.php`), never a native `confirm()`/`alert()`. If the model being deleted has cascading children, pass that computed relationship info in the `'details'` option, plus a `'note'` warning that restoring the parent later won't restore the cascade-deleted children.

## Multi-tenant data in views

Views should only ever render data that was already tenant-scoped by the model/query layer (`App::getCurrentSiteId()` via the `IsModel` trait) — a view is the wrong place to add or double-check tenant filtering; if a view is receiving unscoped data, fix the query that produced it, not the view.

## File header convention (lighter than classes)

Rule 29's file-header DocBlock mandate is written broadly, but in practice view files use a much lighter convention than classes — a plain one-line path comment (`// src/Views/themes/default/layout.php`) rather than the verbose `File:`/`Architectural Purpose:`/`Package:`/`Systemic Role:` block used in `src/Core`, `src/Support`, etc. Match the lightweight comment already used by sibling view files in the same folder; don't invent a heavier header for view files just because Rule 29 doesn't carve out an explicit exception. Views are not classes, so the "Class Block"/"Method DocBlock" parts of Rule 29 don't apply to them at all (unless the view file happens to define a helper function, which should then get a normal function DocBlock).

## Where HTML belongs (Rule 27, the other side of the same coin)

Controllers, models, and service classes must never hardcode multi-line HTML/email bodies — that content belongs in a view file rendered via `Template::renderFile($path, $data)`. If you're tempted to inline a chunk of markup inside a class because "it's small," put it in a view instead; that's exactly the boundary this rule exists to enforce.
