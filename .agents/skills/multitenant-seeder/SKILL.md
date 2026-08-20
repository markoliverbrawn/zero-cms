---
name: multitenant-seeder
description: Explains Zero CMS's self-healing multi-tenant database seeder system (bin/seed, backed by Zero\Support\SeederRunner) — dataset discovery and OOP class seeders. Use when adding/editing a module's Seeders/ folder, modifying bin/seed or SeederRunner.php, or re-seeding the database after a schema change.
---

# Multi-Tenant Database Seeder System (`bin/seed`)

`bin/seed` is a thin entry point backed by `Zero\Support\SeederRunner::run()`, which does the real orchestration (mirrors the `bin/test` / `TestRunner` split — see the `test-suite-architecture` skill). There is no root `seeders/` directory; everything module-specific lives inside that module's own `Seeders/` folder.

## What `bin/seed` actually does

1. Reverts and re-runs every migration (`MigrationManager::down()` then `up()`) to reconstruct the schema from scratch.
2. **Dataset discovery**: scans every `src/Modules/<Module>/Seeders/` folder for a fixed set of recognized filenames — currently just `default.php` — each returning a plain PHP array (**not JSON**) of `sites`/`users`/`pages`/`media`/etc. rows, run in priority order from the map in `discoverDatasetFiles()`. Currently only `Admin` provides one (the base tenant blueprint); any module could.
3. **Class seeder discovery**: scans the same `Seeders/` folders for any `*Seeder.php` class implementing `Zero\Interfaces\SeederInterface` (e.g. `Blog/Seeders/BlogArticleSeeder.php`), sorted by `getPriority()`. After a dataset seeds its sites, every class seeder whose `getModuleId()` is enabled on that site runs against it — this is how a module contributes extra seed content without owning a whole dataset file.
4. Generates time-ordered UUIDv7 identifiers for any row that doesn't specify one, preserving structural ordering on display.
5. Supports selective targeting via `--sites=`/`--only=` (or the `SEED_SITES` env var) and `--zip` for bundling a distributable production package.

## Where things live

- **Orchestrator**: `src/Support/SeederRunner.php` (`Zero\Support\SeederRunner`) + entry point `bin/seed`.
- **Core per-dataset engine**: `Zero\Support\Seeder` — takes one dataset's array and actually writes rows and builds the identity map. Media rows carry their bytes inline as `content_base64`; a module that ships physical seed images keeps them under its own `Seeders/data/images/` folder and copies them in from its class seeder.
- **The base tenant dataset**: `src/Modules/Admin/Seeders/default.php` — the single site, super-admin user, and minimal homepage that `bin/seed` initialises.
- **Other modules' class seeders**: e.g. `src/Modules/Blog/Seeders/BlogArticleSeeder.php`, `src/Modules/Forum/Seeders/ForumPostSeeder.php`, `src/Modules/Shop/Seeders/ShopSeeder.php` — these can `require` their own sibling data files (e.g. `Blog/Seeders/handwritten_articles.php`) that aren't part of the dataset-discovery priority map at all.
