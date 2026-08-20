---
name: php-file-conventions
description: Enforces Zero CMS's PHP coding conventions whenever a new .php file is created, or an existing one is substantially rewritten — classes, controllers, models, migrations, view templates, and helpers. Use when writing or scaffolding any PHP file in this codebase, before considering it done. Checks namespace imports, DocBlocks, method ordering, escaping, template rendering, tenant isolation, error handling, and more.
---

# PHP File Creation Conventions — Enforcement Checklist

Before considering any new or substantially-rewritten `.php` file finished, walk through every applicable item below. Each maps to a numbered rule in `GEMINI.md`'s Part 1 — cite the rule number if you need to explain a rejection or a fix. Skip items that plainly don't apply to the file at hand (e.g. escaping rules don't apply to a migration file), but don't skip an item just because it's inconvenient.

## 1. Namespace & imports (Rule 13)

- [ ] Every referenced class is imported via a `use` statement at the top of the file — never referenced inline by its full namespace (`Validator`, not `\Zero\Core\Validator`).
- [ ] Exception: `Zero\Core\Autoloader` stays fully-qualified inline (`\Zero\Core\Autoloader::init();`) — it has to be `require_once`'d and invoked before any `use`-based autoloading exists.
- [ ] Bare PHP built-ins (`\Exception`, `\RuntimeException`, `\ReflectionClass`, etc.) are not covered by this rule and may stay fully-qualified inline, matching existing convention.

## 2. File & class documentation (Rule 29)

- [ ] The file carries a verbose file-level DocBlock describing its architectural purpose, package, and systemic role (match the existing `File:`/`Architectural Purpose:`/`Package:`/`Systemic Role:` header convention used elsewhere in `src/`).
- [ ] Every class/interface/trait carries a class-level DocBlock detailing its structural responsibilities and parent interfaces.
- [ ] Every method/function carries a DocBlock with `@param`, `@return`, and `@throws` as applicable.

## 3. Class structure (Rule 14)

- [ ] Methods are arranged in strict alphabetical order by name, top to bottom.

## 4. Core architectural integrity (Rule 2)

- [ ] Core directories (`src/Core/`, `src/Http/`) contain no hardcoded module-specific names, schemas, or paths (no `'search'`, `'formbuilder'`, etc. inside core kernel files).
- [ ] New modules/blocks/routes/views/theme-fallbacks are registered dynamically on bootstrap (`Router::register()`, `App::registerViewDir()`, `App::registerThemeFallback()`, `App::registerBlock()`), never hardwired into core.
- [ ] A module's own admin block editor views live inside that module's own `Views/blocks/` folder, passed via `'admin_view'` in its registration config — not centralized inside Admin views.
- [ ] Any class implementing `Zero\Interfaces\Module` defines `getAccentColor(): string`.

## 5. Multi-tenant isolation (Rule 3)

- [ ] Any new table/model that stores tenant-owned data has a `site_id` column.
- [ ] Queries rely on the `IsModel` trait's automatic tenant scoping via `App::getCurrentSiteId()` — no manual, ad-hoc `site_id` filters bolted on separately.

## 6. Validation & escaping

- [ ] Input validation goes through `Zero\Core\Validator` with declarative pipe-syntax rules (Rule 9) — see the `input-validator` skill. Persisted data is filtered through `getValidatedData()` before it touches the database.
- [ ] Any HTML output uses `Zero\Support\Str::escape()`, never raw `htmlspecialchars()` (Rule 28). The one exception is `src/Support/Str.php` itself.
- [ ] Error suppression (`@`) is never used. Every failure-prone operation (disk I/O, DB, network) is checked explicitly and throws a descriptive `Exception` on failure, caught and handled or logged rather than silently swallowed (Rule 25).

## 7. Rendering & security (controllers, models, helpers)

- [ ] Multi-line HTML, email bodies, or UI fragments are never hardcoded inside a controller/model/service class — they're always rendered via `Template::renderFile($path, $data)` from a dedicated view file under `/src/Views/` (Rule 27).
- [ ] External integrations (OAuth, SMTP, any third-party API) use raw `cURL`/sockets — no vendor SDK or package manager dependency (Rule 7).
- [ ] Sensitive block config (e.g. recipient emails) is never exposed to the frontend, even obfuscated — only a stateless `block_id` crosses the wire, resolved server-side (Rule 10).
- [ ] Public form endpoints implement the honeypot pattern: hidden `website_url` input, generic wrapper class name (never containing the word "honeypot"), silent `{"success": true}` drop on a filled trap (Rule 11).
- [ ] Any `BlockHelperInterface` implementation used during indexing/rendering is 100% database-query-free, operating purely on the passed-in JSON block data (Rule 18).

## 8. Data & performance

- [ ] No N+1 query loops: list/catalog/summary views use eager `LEFT JOIN`s or a single batched pre-fetch, never per-row lookups inside a loop or list template (Rule 18).
- [ ] Cascading child relationships (Posts↔Comments, Boards↔Threads, Products↔Variants, Orders↔Order Items, etc.) use the `Zero\Models\Traits\CascadesDeletes` trait with a `$cascadeDeletes` map — never manual cascade-delete loops. Shared/external resources (media, categories) are never cascade-deleted.
- [ ] Timestamps are always written in UTC via `gmdate('Y-m-d H:i:s')` — never a local/server timezone. Any user-facing display of a stored UTC timestamp goes through `I18n::localizeDateTime()` in the view layer, not at write time (Rule 22).

## 9. View templates specifically

If the file is under `src/Views/` or a module's `Views/`, stop and switch to the dedicated `view-template-conventions` skill instead of relying on this checklist alone — it covers how views actually execute (no auto-escaping, theme/prefix resolution, layout wrapping), the lighter file-header convention views use compared to classes, and the full set of template-specific rules (no inline styles/scripts, back-office table markup, N+1 prevention in loops, UTC display localization, block preview sanitizer-safety, and more).

## 10. Don't forget afterward

- [ ] Run the test suite before considering the work done — see the `test-suite-architecture` skill (`docker exec -w /data/misc/zero php83 bin/test`).
