---
name: security-audit
description: Runs a security audit of Zero CMS — the core repo and, critically, any "Zero CMS sub-project" host checkout (e.g. zero-mobsites, zero-d6sites) that requires markoliverbrawn/zero-cms-core via Composer. Runs bin/security-scan over each layer, reads the code for what the scanner can't see, compares framework CVE mechanisms against Zero's code paths, and checks the deployed core version. Use when asked to run/perform a security audit, check for CVEs, or review the codebase for vulnerabilities.
---

# Security Audit — Core & Sub-Projects

Code-level security is gated at merge time by `bin/security-scan` (CI, in core and in sub-project pipelines). The in-app Security module no longer audits code: it runs deployment health checks (`bin/health-check`, the Health Check admin page) for configuration — proxy secret, PHP patch level, served headers, default passwords, installer file, core version, failed sign-ins. This skill is the deeper, periodic review neither of those can do: it runs the scanner, then reads the code for what pattern rules miss.

## The blind spot: sub-projects

A "Zero CMS sub-project" is any checkout whose `composer.json` has `"description": "Zero CMS sub-project"` and `"require": {"markoliverbrawn/zero-cms-core": "..."}` — e.g. `zero-mobsites`, `zero-d6sites`. Its `public/index.php` defines:

```php
define('APPLICATION_ROOT', SUBPROJECT_ROOT . '/vendor/markoliverbrawn/zero-cms-core');
App::registerModulePath(APP_ROOT . '/src/Modules', 'App\\Modules\\');
```

The sub-project's own `App\` code under `SUBPROJECT_ROOT/src/` is the actively-edited, most-likely-to-be-vulnerable layer, and it can reintroduce bug classes core has fixed. Scan it from the sub-project root (`vendor/bin/security-scan src`), and read it by hand per Step 2.

## Step 1 — identify scope

- **Core repo** (this repo, or any checkout of `markoliverbrawn/zero-cms-core` itself): audit the whole tree with Steps 2 and 3.
- **Sub-project**: audit two layers separately:
  1. `SUBPROJECT_ROOT/src/**` (the `App\` namespace) — always audit by hand with Step 2's checks; this is real, sub-project-authored code no existing tool covers.
  2. `vendor/markoliverbrawn/zero-cms-core` — don't re-audit line by line. Instead check what commit/ref it's pinned to (`composer.lock`) and diff that against core's own `CHANGELOG.md`/`git log` for `fix(security)`-tagged entries landed since (e.g. `fix(security): ...` commits). Flag if the vendored copy is behind a known security fix.

## Step 2 — static exploit scan

Run `bin/security-scan`, the CI gate. It tokenises the code and has rules for the bug classes that have actually hit this codebase:

| Rule | Catches |
|---|---|
| `dynamic-php-path` | `.php` paths built from unvalidated variables (block-type / view include traversal) |
| `dynamic-class` | `new $class` / `$class::` on a class name that wasn't validated (stored controller names) |
| `unescaped-output` | view `echo`/`<?=` not wrapped in an escaper/sanitiser (stored XSS) |
| `host-header-link` | request-host reads in files that send email (reset-link poisoning) |
| `unscoped-lookup` | `::find($id)` in controllers instead of `findInCurrentSite()` (cross-tenant IDOR) |
| `unauthenticated-route` | `/admin` and `/api` routes whose controller chain never checks access |
| `sql-interpolation` | variables in `DB::query()` SQL text |
| `timing-compare` | secrets compared with `==`/`===` instead of `hash_equals()` |
| `object-injection` | `unserialize()` without `allowed_classes` |

- Core: `bin/security-scan src`
- Sub-project: `vendor/bin/security-scan src` (core ships it as a Composer bin). A sub-project adopting the scan for the first time can park existing findings with `--write-baseline=security-scan-baseline.json` and run with `--baseline=...` until they're reviewed.

Then go beyond it: the rules are heuristics. **Read the code** for what they can't see:
- authorisation logic (who can grant what, e.g. role escalation)
- sanitiser and parser correctness (try known bypass payloads)
- business-logic flaws and protocol handling (SMTP, OAuth)

Check the `security-scan-ignore` comments too: report any whose stated reason doesn't hold, and any baselined findings that turn out to be real.

## Step 3 — framework CVE comparative audit

Compare against three reference packages — `laravel/framework`, `symfony/security-core`, `wordpress/core` — extending the list only if the user names another framework. Fetch advisories via OSV:

```
curl -s -X POST https://api.osv.dev/v1/query -H 'Content-Type: application/json' \
  -d '{"package":{"name":"laravel/framework","ecosystem":"Packagist"}}'
```

`wordpress/core` isn't a real Packagist package, so OSV will likely return an empty `vulns` list for it — fall back to `WebSearch` for recent WordPress CVEs instead of treating the empty result as "no known WordPress vulnerabilities."

For each advisory returned, don't just assert immunity — trace the actual vulnerable *mechanism* (mass assignment, deserialization, auth bypass, path traversal, timing side-channel, dependency-chain compromise, …) to the concrete Zero/sub-project code path playing the analogous role, and check it directly. Known structural mitigations already established in this codebase (verify they still hold, don't cite from memory) — no `unserialize()` usage, `DB::query()` bound-parameter discipline, `hash_equals()` for token/signature comparisons, no vendor supply chain since core has zero runtime dependencies. A sub-project layer can reintroduce any of these even though core is hardened.

## Step 4 — report

Write the report with these sections:

1. Executive Summary
2. Discovered Vulnerabilities & Warnings — severity-classified, exact `file:line`, concrete remediation
3. Framework CVE Comparative Audit
4. Architecture Strengths
5. Strategic Roadmap

Don't give an overall numeric score. Report severity per finding (Critical/High/Medium/Low/Info). For configuration of a live deployment, point to that site's Health Check results rather than guessing.

## Notes

- No automatic persistence: an audit run through this skill is not saved anywhere unless the user asks for a file to be written.
