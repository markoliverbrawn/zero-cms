---
name: module-creation
description: Explains how to scaffold a new decoupled module under src/Modules/<Name>/ — the Module.php contract, auto-discovery, migrations, routes, blocks, sidebar links, per-module Lang dictionaries, and per-tenant enable/disable. Use when adding a new module, registering a Module class, or debugging why a module's routes/views/blocks/sidebar links/translations aren't showing up.
---

# Creating a New Module

A module is a self-contained feature package under `src/Modules/<Name>/` that the core discovers and wires up automatically — core code never hardcodes anything module-specific (Rule 2 in `GEMINI.md`/`php-file-conventions`). Every file you create inside a module is still a normal PHP file/view — this skill only covers the module-specific scaffolding and registration; see `php-file-conventions` and `view-template-conventions` for the rest.

## Minimum required layout

```
src/Modules/<Name>/
├── Module.php          # implements Zero\Interfaces\Module — the only file discovery requires
└── Lang/               # MANDATORY once the module renders any user-facing string
    ├── en.php
    ├── es.php
    ├── hr.php
    └── mi.php
```

`Module.php` is the only file *discovery* requires, but `Lang/` is **mandatory** for any module with user-facing text — see "Translations" below. Everything else is optional and purely convention-based — add the folders only as you need them.

## The `Module.php` contract

`src/Modules/<Name>/Module.php` must declare `namespace Zero\Modules\<Name>;` and `class Module implements Zero\Interfaces\Module`, implementing:

* `getId(): string` — a unique lowercase identifier (e.g. `'blog'`, `'shop'`). This is the key the whole system uses: `enabled_modules` site config, `module_dependency` sidebar gating, and view/route registration all key off this string.
* `getAccentColor(): string` — a brand hex color (e.g. `'#3b82f6'`) used for the module's admin pills/widgets.
* `getDashboardWidgetView(): ?string` — the view name (relative to the module's `Views/`) of its dashboard widget, or `null` if it doesn't have one.
* `getRoutes(): array` — a map of `'#^/regex/pattern$#' => ControllerClass::class`. Route patterns are plain regex; capture groups become controller action arguments.
* `getMigrationClass(): ?string` — can return `null` in nearly every real module. Actual migrations are discovered by filename glob (see "Migrations" below), not by this method — the framework never consumes its return value elsewhere. Only bother returning a real FQCN if you have a specific reason to expose "the" migration class for this module to other code.
* `init()` (optional, not part of the interface, but always called if present) — where you register blocks, models, sidebar links, scheduled jobs, and any cross-module integration checks. This is where nearly all of a module's registration actually happens.

## Discovery — what happens automatically, with zero manual wiring

`App::discoverAndRegisterModules()` scans every directory in `App::getModuleSearchPaths()` (the bundled `src/Modules/` plus anything a host project added via `App::registerModulePath()`), looks for `<Namespace>\<FolderName>\Module`, and for every class that implements `Zero\Interfaces\Module`:

1. Registers the module's own namespace with the Router (via reflection — you don't declare this yourself).
2. Registers its routes: `Router::register($module->getRoutes(), null, $module->getId())`.
3. If a `Views/` folder exists inside the module directory, registers it as a view prefix matching the module's ID — this is what makes `App::render('<id>/...')`-style resolution work, and pairs with any `layout.php` you put in that same `Views/` folder.
4. Calls `$module->init()` if it exists.

You never call any of this yourself for a module you're creating — just implement the interface correctly and put files in the conventional places, and discovery finds them.

## Migrations

Put migration files under `src/Modules/<Name>/Database/Migrations/`, named `NNNN_Description.php` (a global, cross-module sequential number — check the highest existing number across `src/Database/Migrations/` and every other module before picking the next one, since ordering is a single flat sequence, not per-module). `MigrationManager` discovers every module's migrations with a glob (`<modulesDir>/*/Database/Migrations/[0-9]*_*.php`) and runs them in filename order alongside core's own — this is completely independent of `getMigrationClass()`. See the `db-schema-blueprint` skill for the broader schema picture (and its known-stale caveat).

## Translations — every module owns its own strings (mandatory)

**A module must never add strings to the core dictionaries in `src/Lang/`.** Core is module-agnostic (Rule 2): `src/Lang/<code>.php` holds only generic, framework-level vocabulary (`title`, `slug`, `status`, `created_at`, `no_records_found`, and the helper texts for core's own `Page`/`Media`/`User`/`Site` models). Any string that only exists because your module exists belongs to your module.

Put it in `src/Modules/<Name>/Lang/<code>.php`, returning a flat `key => string` array:

```php
// src/Modules/Shop/Lang/en.php
return [
    'product_name' => 'Product Name',
    'sku_help'     => 'The Stock Keeping Unit code for inventory tracking and shipping.',
];
```

* **No registration needed.** `I18n::init()` scans every directory in `App::getModuleSearchPaths()` for `<Module>/Lang/<activeLang>.php` and merges each one it finds. Host-project module paths registered via `App::registerModulePath()` are scanned too. There is no `init()` hook to write and no `I18n::register()` call to make — the file on disk *is* the wiring.
* **Ship all four languages.** Rule 6 commits the platform to English, Spanish, Croatian, and Māori. There is **no per-key fallback to English** — a key missing from the active language's file renders as the raw key string (`forum_boards`) in the UI. So create `en.php`, `es.php`, `hr.php`, and `mi.php` together, with the same key set in each.
* **Discovery ignores per-tenant enablement.** Lang files load from every module folder present on disk, whether or not the current site has the module enabled. That is deliberate — it means a sibling module (or Admin) can safely label a cross-module link without the string going missing.
* **Prefix your keys with your module id.** Every module dictionary `array_merge`es into one flat bucket, so there is no automatic namespacing — two modules defining `category` collide and the winner is whichever one `scandir` reached last. Name keys `forum_boards`, not `boards`. The one exception is field-derived keys: `{field}_help` / `{field}_desc` are built from the model field name at render time (Rule 6), so they cannot carry a prefix — they inherit whatever the field is called, which is exactly where two modules owning a same-named field will clash.
* **Core wins on collisions.** `I18n::translate()` resolves core (`$translations`) *before* module dictionaries (`$customTranslations`), so a key defined in both silently uses core's value and your module file becomes dead weight. If you move a string out of core, delete the core copy.
* **`src/Integration/Tests/ModuleLangDictionaryTest.php` enforces all of the above** — it fails the build on a cross-module key collision, a module key already present in core, a module-prefixed key added to core, a missing or key-incomplete language file, and a new non-derived key without its module-id prefix. Unprefixed keys that predate the convention are grandfathered in that file's `$legacyUnprefixed` map; **prefix your new key rather than adding to that list.**
* **Field helper texts follow the field name.** `AbstractFormField::resolveHelperText()` derives `{field}_help` / `{field}_desc` from the model field name (Rule 6), so a model field your module declares needs its `_help` key in *your* `Lang/` files, not core's. Pass `'guessHelperTextKey' => false` for end-user-named fields (as FormBuilder does) so an unrelated key can't leak in.

## Routes & per-tenant enable/disable

Routes are always *registered*, but a request only actually reaches your controller if the current site has your module enabled: `HandlesRequests` checks `$site->isModuleEnabled($module->getId())` before dispatching. You don't need to add this check yourself in every controller — it's centralized. Sites toggle modules via the `enabled_modules` JSON column (see the `db-schema-blueprint` skill).

## Registering things from `init()`

* **Blocks**: `App::registerBlock($type, ['label' => ..., 'icon' => ..., 'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/{type}.php', 'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/{type}.php'])`. Keep the block's own admin/frontend view files inside your module's `Views/blocks/` — never centralize them into Admin's views (Rule 2). See the `page-builder-engine` skill for the full block rendering/serialization mechanism, and `view-template-conventions` for how to write the view files themselves.
* **Models**: `App::registerModel($name, ModelClass::class)` — makes the model addressable by name for the generic admin list/edit controllers (e.g. `/admin/list/{name}`).
* **Admin sidebar links**: `App::registerAdminSidebarLink($sectionId, ['title' => ..., 'url' => ..., 'icon' => ..., 'module_dependency' => $this->getId(), 'precedence' => 10])`. Always set `module_dependency` to your own module's ID so the link automatically hides on sites where the module is disabled — this is the actual enforcement point for sidebar visibility, distinct from the route-dispatch check above.
* **Scheduled jobs**: `Scheduler::register(SomeJob::class, [], 'daily')` (see the Queue module for the scheduler's own conventions).
* **Cross-module integration**: guard optional integrations with `class_exists(SomeOtherModule\Service::class)` before calling into another module (see Blog's `SearchService::register()` call) — a module must degrade gracefully if an optional sibling module isn't installed/enabled, never hard-depend on it.

## Suggested fuller layout (add only what you need)

```
src/Modules/<Name>/
├── Module.php
├── Controllers/                 # incl. Controllers/Api/ for JSON endpoints
├── Models/
├── Lang/                       # en.php, es.php, hr.php, mi.php — mandatory, see "Translations"
├── Database/Migrations/
├── Views/                       # auto-registered as a view prefix if present
│   ├── layout.php                # optional — pairs with prefix-resolved views
│   └── blocks/{admin,frontend}/  # per-block admin+frontend partials
├── Jobs/                        # queue-dispatched or scheduled jobs
├── Seeders/                     # module-specific seed data, if any
└── Tests/                       # src/Modules/<Name>/Tests/*Test.php — see test-suite-architecture
```

## Don't forget

* Ship `Lang/{en,es,hr,mi}.php` for every user-facing string the module renders, and confirm none of them were added to `src/Lang/` instead.
* Write at least one test under `src/Modules/<Name>/Tests/` — see the `test-suite-architecture` skill for the exact bootstrap-require convention (a direct require to `src/Support/TestBootstrap.php`, 3 `dirname()` levels deep for module tests).
* Run `docker exec -w /data/misc/zero php83 bin/test` before considering the module done.
