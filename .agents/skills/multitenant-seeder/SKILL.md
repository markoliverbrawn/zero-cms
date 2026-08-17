---
name: multitenant-seeder
description: Explains Zero CMS's self-healing multi-tenant database seeder system (bin/seed, backed by Zero\Support\SeederRunner) — dataset discovery, OOP class seeders, and documentation-seeding conventions. Use when adding/editing a module's Seeders/ folder, modifying bin/seed or SeederRunner.php, or re-seeding the database after a schema/documentation change.
---

# Multi-Tenant Database Seeder System (`bin/seed`)

`bin/seed` is a thin entry point backed by `Zero\Support\SeederRunner::run()`, which does the real orchestration (mirrors the `bin/test` / `TestRunner` split — see the `test-suite-architecture` skill). There is no root `seeders/` directory; everything module-specific lives inside that module's own `Seeders/` folder.

## What `bin/seed` actually does

1. Reverts and re-runs every migration (`MigrationManager::down()` then `up()`) to reconstruct the schema from scratch.
2. **Dataset discovery**: scans every `src/Modules/<Module>/Seeders/` folder for a fixed set of recognized filenames — `default.php`, `documentation.php`, `kitchensink.php` — each returning a plain PHP array (**not JSON**) of `sites`/`users`/`pages`/`media`/etc. rows. Runs them in priority order (`default.php` first, then `documentation.php`, then `kitchensink.php`). Currently only `DemoGenerator` provides these; any module could.
3. **Class seeder discovery**: scans the same `Seeders/` folders for any `*Seeder.php` class implementing `Zero\Interfaces\SeederInterface` (e.g. `Blog/Seeders/BlogArticleSeeder.php`), sorted by `getPriority()`. After a dataset seeds its sites, every class seeder whose `getModuleId()` is enabled on that site runs against it — this is how a module contributes extra seed content without owning a whole dataset file.
4. Generates time-ordered UUIDv7 identifiers for any row that doesn't specify one, preserving structural ordering on display.
5. Supports selective targeting via `--sites=`/`--only=` (or the `SEED_SITES` env var) and `--zip` for bundling a distributable production package.

## Where things live

- **Orchestrator**: `src/Support/SeederRunner.php` (`Zero\Support\SeederRunner`) + entry point `bin/seed`.
- **Core per-dataset engine**: `Zero\Support\Seeder` — takes one dataset's array and actually writes rows, copies seed media, and builds the identity map. Physical seed images/videos it copies from are resolved from `src/Modules/DemoGenerator/Seeders/data/{images,videos}/` (hardcoded — currently the only source of seed media in the codebase).
- **DemoGenerator's own datasets**: `src/Modules/DemoGenerator/Seeders/{default,documentation,kitchensink}.php`, plus its supporting assets under `src/Modules/DemoGenerator/Seeders/data/` (images, videos, AI-generated images, the documentation sitemap).
- **Other modules' class seeders**: e.g. `src/Modules/Blog/Seeders/BlogArticleSeeder.php`, `src/Modules/Forum/Seeders/ForumPostSeeder.php`, `src/Modules/Shop/Seeders/ShopSeeder.php` — these can `require` their own sibling data files (e.g. `Blog/Seeders/handwritten_articles.php`) that aren't part of the dataset-discovery priority map at all.
- **One-off content-authoring tools** (not part of the `bin/seed` pipeline, run manually): `bin/generate-demo-images` (regenerates the Guide site's AI blog illustrations) and `bin/seed-kitchensink-mass` (bulk-generates stress-test content for the kitchensink demo site).

## Documentation seeding convention

Whenever a new feature, module, or system capability is added or substantially updated, it must be fully documented in the Guide site's seeder map and the database must be cleanly re-seeded (see Rule 15 in `GEMINI.md`):

- Register dedicated technical page records under the `pages` array inside `src/Modules/DemoGenerator/Seeders/documentation.php`, covering the feature's architectural summary, configuration/`.env` info, and extension how-tos.
- Update the Mermaid sitemap document (`src/Modules/DemoGenerator/Seeders/data/sitemaps/documentation.md`) whenever new seeder data is created or the page tree hierarchy changes, so the guide site's interactive sitemap stays accurate.
- Sub-pages seeded beneath `modules/` or `how-tos/` must set `"show_in_nav" => "0"` to stay out of primary navigation, accessible only as sub-pages of their index.
- After editing a dataset file, re-run the seeder inside the container to persist and verify the new pages: `docker exec -w /data/misc/zero php83 bin/seed`.
