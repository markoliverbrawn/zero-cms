---
name: multitenant-seeder
description: Explains Zero CMS's self-healing multi-tenant database seeder system (seeders/seeder.php) — revert, migrate, and re-seed from JSON tenant maps. Use when modifying seeder data under seeders/data/, editing seeders/seeder.php, or re-seeding the database after a schema/documentation change.
---

# Multi-Tenant Database Seeder System (`seeders/seeder.php`)

The seeder architecture is fully structured, self-healing, and reproducible:

1. Reverts all tables sequentially from bottom to top (Shop, Blog, then Core CMS).
2. Re-runs database migrations sequentially from top to bottom.
3. Decodes tenant JSON seeder maps inside `seeders/data/` (e.g. `shop.json`, `documentation.json`) and loops over row matrices.
4. Generates time-ordered UUIDv7 identifiers if they are not explicitly specified inside rows (preserving structural ordering of items during query display).
5. Automatically bundles and compiles zipped package distributions of site data for production environments, concluding with 100% success states.

## Documentation seeding convention

Whenever a new feature, module, or system capability is added or substantially updated, it must be fully documented in the Guide site's seeder map and the database must be cleanly re-seeded (see Rule 15 in `GEMINI.md`):

- Register dedicated technical page records under the `pages` array inside `seeders/data/documentation.json`, covering the feature's architectural summary, configuration/`.env` info, and extension how-tos.
- Update the Mermaid sitemap document (`seeders/data/sitemaps/documentation.md`) whenever new seeder data is created or the page tree hierarchy changes, so the guide site's interactive sitemap stays accurate.
- Sub-pages seeded beneath `modules/` or `how-tos/` must set `"show_in_nav": "0"` to stay out of primary navigation, accessible only as sub-pages of their index.
- After editing seeder JSON, execute the seeder inside the container to persist and verify the new pages: `docker exec -w /data/misc/zero php83 php seeders/seeder.php`.
