---
name: security-audit
description: Runs a security audit of Zero CMS — the core repo and, critically, any "Zero CMS sub-project" host checkout (e.g. zero-mobsites, zero-d6sites) that requires markoliverbrawn/zero-cms-core via Composer. Replicates the in-app Security module's static exploit scan (src/Modules/Security/Services/ExploitScanner.php) and OSV CVE comparative audit against Laravel/Symfony/WordPress (src/Modules/Security/Services/CveFetcherService.php), and extends both to sub-project-owned code the in-app tool can't reach. Use when asked to run/perform a security audit, check for CVEs, or review the codebase for vulnerabilities.
---

# Security Audit — Core & Sub-Projects

Zero CMS already ships an in-app audit (`bin/security-audit` → `SecurityAuditController` → `ExploitScanner` + `CveFetcherService`, with results rendered by an AI prompt and archived to `security_audits`). This skill is how *you* perform the equivalent audit directly — by running that tooling where it applies, and by hand where it structurally can't reach, most importantly inside sub-projects.

## The blind spot: sub-projects

A "Zero CMS sub-project" is any checkout whose `composer.json` has `"description": "Zero CMS sub-project"` and `"require": {"markoliverbrawn/zero-cms-core": "..."}` — e.g. `zero-mobsites`, `zero-d6sites`. Its `public/index.php` defines:

```php
define('APPLICATION_ROOT', SUBPROJECT_ROOT . '/vendor/markoliverbrawn/zero-cms-core');
App::registerModulePath(APP_ROOT . '/src/Modules', 'App\\Modules\\');
```

`ExploitScanner::SCAN_PATHS` is resolved relative to `APPLICATION_ROOT`. In a sub-project that constant points at the **vendored core copy**, not the sub-project's own code — so running `bin/security-audit` from inside a sub-project only ever rescans core. It never sees the sub-project's own `App\` code under `SUBPROJECT_ROOT/src/{Controllers,Models,Modules,Support,Views}`, which is the actively-edited, most-likely-to-be-vulnerable layer. This skill exists specifically to cover that gap — always audit it by hand per Step 2 below.

## Step 1 — identify scope

- **Core repo** (this repo, or any checkout of `markoliverbrawn/zero-cms-core` itself): audit the whole tree; `bin/security-audit` covers it natively if you can boot the app (DB configured). Otherwise replicate Step 2/3 by hand.
- **Sub-project**: audit two layers separately:
  1. `SUBPROJECT_ROOT/src/**` (the `App\` namespace) — always audit by hand with Step 2's checks; this is real, sub-project-authored code no existing tool covers.
  2. `vendor/markoliverbrawn/zero-cms-core` — don't re-audit line by line. Instead check what commit/ref it's pinned to (`composer.lock`) and diff that against core's own `CHANGELOG.md`/`git log` for `fix(security)`-tagged entries landed since (e.g. commits like "Security remediations", "Implement security audit job, CVE fetcher, and exploit scanner"). Flag if the vendored copy is behind a known security fix.

## Step 2 — static exploit scan

Replicate `ExploitScanner`'s four checks (it tokenizes to dodge false positives from strings/comments — do the same sanity pass on any grep hit before flagging it):

1. **Object injection** — `unserialize(` called as a bare function (not `->unserialize()`/`::unserialize()`/a method definition) on anything that isn't fully trusted local data.
2. **SQL injection** — `DB::query("...")` whose first argument interpolates a raw `$variable` that isn't one of the pre-built-safe SQL fragment names (`$sql`, `$query`, `$whereSql`, `$params`, etc. — see `ExploitScanner::$safeVariables` for the full list). Bound placeholders (`?`) are the only acceptable way user input reaches a query.
3. **Timing attack** — `==`/`===` comparing a variable named like `token`/`signature`/`csrf` against anything, in a file that never calls `hash_equals(`.
4. **Path traversal** — `file_get_contents`/`file_put_contents`/`unlink` on a dynamic path with no `realpath()` confinement nearby (exceptions: reading `php://input`, and core's own low-level storage drivers).

`grep -rn` for a fast first pass across the relevant tree (core paths: `src/Core/ src/Http/ src/Models/ src/Support/ src/Modules/`; sub-project: `src/Controllers/ src/Models/ src/Modules/ src/Support/ src/Views/`), then read each hit in context.

## Step 3 — framework CVE comparative audit

Compare against the same three reference packages `CveFetcherService` benchmarks against — `laravel/framework`, `symfony/security-core`, `wordpress/core` — extending the list only if the user names another framework. Fetch advisories the same way the app does, via OSV:

```
curl -s -X POST https://api.osv.dev/v1/query -H 'Content-Type: application/json' \
  -d '{"package":{"name":"laravel/framework","ecosystem":"Packagist"}}'
```

`wordpress/core` isn't a real Packagist package, so OSV will likely return an empty `vulns` list for it (matches the app's own behavior) — fall back to `WebSearch` for recent WordPress CVEs instead of treating the empty result as "no known WordPress vulnerabilities."

For each advisory returned, don't just assert immunity — trace the actual vulnerable *mechanism* (mass assignment, deserialization, auth bypass, path traversal, timing side-channel, dependency-chain compromise, …) to the concrete Zero/sub-project code path playing the analogous role, and check it directly. Known structural mitigations already established in this codebase (verify they still hold, don't cite from memory) — no `unserialize()` usage, `DB::query()` bound-parameter discipline, `hash_equals()` for token/signature comparisons, no vendor supply chain since core has zero runtime dependencies. A sub-project layer can reintroduce any of these even though core is hardened.

## Step 4 — report

Mirror the sections `SecurityAuditController`'s prompt asks for, but write them directly yourself:

1. Executive Summary
2. Discovered Vulnerabilities & Warnings — severity-classified, exact `file:line`, concrete remediation
3. Framework CVE Comparative Audit
4. Architecture Strengths
5. Strategic Roadmap

Don't fabricate the 0–100 `calculated_score` — that's a specific formula in `SecurityAuditController::calculateScore()` tied to live telemetry (install-file lock state, default admin password, storage-dir protection, finding count, …) that a manual code read can't reproduce. Only cite a real score if you actually ran `bin/security-audit` and it computed one; otherwise report severity qualitatively (Critical/High/Medium/Low/Info per finding).

## Notes

- No automatic persistence: the in-app AJAX flow auto-archives to `security_audits`; a manual audit run through this skill does not persist anywhere unless the user explicitly asks for a file to be written.
