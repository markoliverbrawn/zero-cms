# Zero CMS — Purist Enterprise Architecture & Security Ledger

```text
  ███████╗███████╗██████╗  ██████╗      ██████╗███╗   ███╗███████╗
  ╚══███╔╝██╔════╝██╔══██╗██╔═══██╗    ██╔════╝████╗ ████║██╔════╝
    ███╔╝ █████╗  ██████╔╝██║   ██║    ██║     ██╔████╔██║███████╗
   ███╔╝  ██╔══╝  ██╔══██╗██║   ██║    ██║     ██║╚██╔╝██║╚════██║
  ███████╗███████╗██║  ██║╚██████╔╝    ╚██████╗██║ ╚═╝ ██║███████║
  ╚══════╝╚══════╝╚═╝  ╚═╝ ╚═════╝      ╚═════╝╚═╝     ╚═╝╚══════╝
  "Zero Dependencies. Zero Bloat. Absolute Structural Integrity."
```

## Introduction & Purist Manifesto

**Zero CMS** is a zero-dependency, ultra-high-performance, multi-tenant content management system and transactional e-commerce platform. In an era dominated by bloated, nested package-manager architectures and vulnerable dependency spiders, Zero CMS takes a radical return to fundamental software engineering principles:

* **Zero Third-Party Runtime Dependencies:** No NodeJS, no npm, no Tailwind, no third-party framework wrappers, no `vendor/autoload.php` in the request path — everything runs on bare-metal native PHP and raw SQL. Composer is used solely as a versioned install/update mechanism for embedding Core into a host project (see `bin/create-project`), never as a runtime dependency manager.
* **Instantaneous execution (Sub-1ms):** Bypasses all middleware boot latency and array-scanning dispatchers.
* **Hardened Security Boundaries:** Engineered to resist AI-automated vulnerability scans via zero-dependency CSRF, input recursive sanitization, honed honeypots, rate limiters, and strict multi-tenant Active-Record scoping.

---

## 1. System Bootstrapping & Cascading MVC Architecture

Zero CMS routes and resolves multi-tenant contexts in a single, highly optimized workflow:

```text
[HTTP Web Request]
       │
       ▼
[index.php (Front Controller Gateway)]
       │
       ▼
[App::bootstrap() - UNION ALL Context Extraction]
       │
       ├──► Query DB (UNION ALL): Site Configuration + Authenticated User
       ▼
[Router::handle() - Route Pattern Matcher]
       │
       ├──► Is module enabled for active Site?
       │     ├── YES: Load module controllers & Views
       │     └── NO : Skip route mapping (Return 404)
       ▼
[View Cascading Resolver]
       ├──► Check `/src/Views/themes/{activeSiteTheme}/{template}.php`
       └──► Fallback to `/src/Views/themes/default/{template}.php` (Cascading inheritance)
```

### Single-Query UNION ALL Bootstrapping
On bootstrap, Zero CMS resolves the active tenant Site details (via `HTTP_HOST`) and the logged-in User profile (via session `user_id`) in a **single-query database roundtrip** using a consolidated `UNION ALL` query, avoiding redundant TCP connection overhead. `App.php` is a thin shell composed of focused traits under `src/Core/Concerns/`; the query itself lives in `ResolvesTenantContext::bootstrapFetchSiteAndUser()` (inside `src/Core/Concerns/ResolvesTenantContext.php`):

```sql
SELECT
    'site' AS record_type, id, name, domain, theme, enabled_modules,
    homepage_id, timezone, default_language,
    NULL AS email, NULL AS password_hash, NULL AS role, NULL AS site_id, NULL AS preferences,
    created_at, updated_at
FROM sites WHERE domain = ?
UNION ALL
SELECT
    'user' AS record_type, id, username AS name, NULL AS domain, NULL AS theme, NULL AS enabled_modules,
    NULL AS homepage_id, NULL AS timezone, NULL AS default_language,
    email, password_hash, role, site_id, preferences,
    created_at, updated_at
FROM users WHERE id = ?
```

---

## 2. Platform Modules

Zero CMS is divided into fully decoupled, modular plug-ins under `src/Modules/`:

1. **Pages & Dynamic Layout Block Builder (`Admin`):**
   Pages are stored in the database as serialized JSON arrays of content blocks (e.g. Accordions, Galleries, Testimonials, Sub-Pages). Adding a block admin view automatically registers editing schemas generically in the back-office, while custom blocks render on the frontend cascadingly.
2. **Form Builder & archival Submissions (`FormBuilder`):**
   Enables designers to construct customized forms inside the page builder dynamically. Submissions are validated through `Validator` and archived securely as dynamic JSON structures, preserving exact submission history even if the source page or form is later deleted.
4. **Platform Security Hardening & AI Auditing (`Security`):**
   Integrates globally enforced Content Security Policy (CSP), defensive HTTP shielding, secure forced password updates, security audit logging, and automated telemetry threat-modeling with Google's generative AI.
5. **Background Jobs & Task Scheduler (`Queue`):**
   Dispatches and processes queued jobs (e.g. `PurgeOldLogsJob`) via `QueueManager` and a cron-driven `Scheduler`, run out-of-band through the `bin/queue-runner` and `bin/scheduler` CLI entry points rather than inline on the request path.
6. **Site Search (`Search`):**
   Decoupled search-driver architecture (`SearchDriverInterface`) with a `DatabaseSearchDriver` implementation, exposed via `SearchController`/`SearchService` and a `Searchable` model trait.

---

## 3. Directory Layout Map

```text
/data/misc/zero/
├── GEMINI.md                    # System guidelines, coding standards, and developer rules
├── public/                       # Webroot (document root points here)
│   ├── index.php                 # Central Front-Controller Gateway
│   ├── assets/                   # Publicly accessible static assets
│   │   └── css/                  # Modular style files (admin.css)
│   └── storage/                  # Public-facing symlink/passthrough for uploaded media
│       ├── uploads/              # Tenant-scoped originals, exactly as uploaded
│       └── variants/             # Derived image renditions (disposable cache, never mixed with uploads)
├── bin/                          # CLI entry points (bin/seed, bin/test, bin/migrate, bin/assets, bin/queue-runner, bin/scheduler, etc.)
├── storage/                      # Private file storage (uploads, logs)
├── src/                          # OOP core framework engine
│   ├── Core/                     # Kernel, Bootstrapping (App.php + Concerns/ traits), Env, Validator, Storage/ drivers (local, AWS S3, Google Cloud Storage)
│   ├── Database/                 # Connection (DB.php), Migration/MigrationManager, numbered Migrations/
│   ├── Http/                     # HTTP infrastructure, Router, Middleware, Controllers
│   ├── Integration/               # Cross-module integration test suite (Tests/ only)
│   ├── Interfaces/               # Strict OOP contracts
│   ├── Lang/                     # i18n translation tables (en.php, es.php, hr.php, mi.php)
│   ├── Models/                   # Core active-record entities (Media, Page, Site, User)
│   ├── Modules/                  # Decoupled extensible modules
│   │   ├── Admin/                # Unified Back-Office dashboard controller & views
│   │   ├── FormBuilder/          # Dynamic forms creation and submission logs module
│   │   ├── Queue/                # Background job queue & cron-style scheduler
│   │   ├── Search/               # Decoupled site search driver architecture
│   │   └── Security/             # Platform security hardening & AI threat auditing module
│   ├── Services/                 # Cross-cutting services (e.g. AiService + Ai/Providers)
│   ├── Support/                  # Security, Logger, Emailer, Seeder/SeederRunner, TestRunner, Str, I18n, Assets/ImageProcessor/VariantCache
│   └── Views/                    # Cascading theme templates (themes/{site theme}/, themes/default/)
```
Per-component tests live alongside the code they cover (e.g. `src/Core/Tests/`, `src/Modules/Search/Tests/`) rather than in a single top-level `tests/` directory — see Section 6.

---

## 4. On-Demand Image Variants

Templates never reference an original image directly. They ask `Zero\Support\Assets` for the
rendition they actually need, and the engine produces it the first time a browser requests it:

```php
<img src="<?php echo Assets::url($mediaUrl, width: 600, height: 450); ?>"
     srcset="<?php echo Str::escape(Assets::srcset($mediaUrl, [400, 600, 900], 4 / 3)); ?>"
     sizes="(max-width: 600px) 100vw, 300px"
     loading="lazy" decoding="async" alt="" />
```

Supplying both dimensions crops to fill, positioned on the image's stored focal point
(`media.focus_x` / `focus_y`), so the subject stays in frame instead of being centre-cropped out
of it. Supplying one scales proportionally without cropping. Renditions are never upscaled past
their source. Arguments are designed to be passed by name.

**Nothing that cannot be resized safely is rewritten.** Animated GIFs, SVGs, videos, non-image
files, access-gated private media and paths with no media record behind them are all returned
untouched, so `Assets::url()` is safe to wrap around every image URL unconditionally.

### How it stays fast

| Stage | Cost |
| :--- | :--- |
| `Assets::url()` during render | Pure computation — no filesystem stat, no query, no network |
| Extra queries per page | **Zero.** The existing batched block-media eager-load primes the registry |
| Warm HTTP request | Static file served by the web server; PHP is never invoked |
| Cold HTTP request | One primary-key lookup plus one GD render — once per rendition, ever |

The URL *is* the cache path (`/storage/variants/{site}/{shard}/{media}/{w}x{h}-{fit}-q{q}-{sig}.webp`).
A rewrite rule in `public/.htaccess` lets the web server satisfy the request off disk whenever that
file exists and falls through to the front controller only on a miss, where
`MediaVariantController` renders it, publishes it by atomic rename, and streams it back with
far-future immutable caching headers.

### Signed URLs and invalidation

Every URL embeds a truncated HMAC (keyed by `APP_KEY`) over the tenant, media id, dimensions, fit
mode, quality, and the source's version stamp. Two properties follow:

* **Only renditions the CMS itself minted can be generated.** The endpoint cannot be walked with
  arbitrary dimensions to burn CPU or fill the disk; an unsigned request is a flat 404 before any
  image work happens. A cross-tenant replay is refused for the same reason.
* **Editing an image rotates its URLs.** Because the signature covers `media.updated_at`, moving a
  focal point or replacing a file republishes every rendition under new URLs, which is what makes
  the `immutable` caching header truthful rather than optimistic.

Superseded files therefore stop being referenced rather than becoming stale. `bin/assets` reclaims
them:

```bash
bin/assets stats                              # cache file count and disk usage
bin/assets prune --older-than=90d             # drop renditions nothing has requested lately
bin/assets warm --site=<uuid> --widths=400,800  # pre-render a ladder after a bulk import
bin/assets clear [--site=<uuid>]              # discard the cache entirely
```

The cache is a deliberate sibling of the uploads tree, not a folder inside it: the media library
only ever lists uploads, deleting a media record only ever touches its own object, and the whole
cache can be discarded without endangering an original. Under the cloud storage drivers a variant
is written both to local disk (a hot per-instance cache the web server can serve statically) and
to the configured bucket (the durable copy a freshly started instance rehydrates from instead of
re-rendering); putting a CDN in front is recommended there.

---

## 5. System Security & Threat Model

The security boundaries of Zero CMS are rigorously isolated. Below is the **Threat Model & trust Boundaries Diagram** representing request validation, isolation checks, and security remediations:

### 1. Threat Model & Trust Boundaries Diagram

```mermaid
flowchart TD
    subgraph PUBLIC_ZONE [Trust Boundary: Untrusted Public Client]
        A[Browser / Visitor]
    end

    subgraph SYSTEM_CORE [Trust Boundary: Zero CMS Kernel]
        B[CsrfMiddleware] -- "Validate POST state" --> C[RateLimitMiddleware]
        C -- "Throttle requests" --> D[Input Sanitizer]
        D -- "Strip tags / recursively clean" --> E[Validator]
        E -- "Filter un-declared fields" --> F[App::getCurrentSiteId]
    end

    subgraph AUTH_ZONE [Trust Boundary: Auth & Isolation Check]
        G[AuthMiddleware] -- "Check session user_id" --> H["App::applyRoleMiddleware()"]
        H -- "Restrict to editor/super_admin" --> I[Site Isolation Check]
        I -- "site_id == CurrentSiteId" --> J[Active Record Operations]
    end

    subgraph DATA_ZONE [Trust Boundary: Data Storage & Sockets]
        K[(PDO MySQL DB Engine)]
        L[Email SMTP TCP Socket]
    end

    A -- "1. POST Request" --> B
    B -- "CSRF Mismatch" --> B_ERR[Wipe Session & Redirect to Login]
    C -- "Rate Limit Exceeded (429 + Retry-After)" --> C_ERR[Logger::log audit trail]
    F -- "2. Scope Active Tenant" --> G
    J -- "3. Query via IsModel active-record trait" --> K
    J -- "4. Dispatch Notifications" --> L
```

### 2. STRIDE Threat Model & Source Code Remediation Matrix

Zero CMS is engineered under a strict, zero-trust security blueprint. Below is the formal **STRIDE** security threat analysis mapped directly to the actual mitigation systems implemented in the codebase:

| Threat Category | Specific System Risk | Direct Source Code Mitigation & Remediation |
| :--- | :--- | :--- |
| **S**poofing | **CSRF & Session Hijacking:** Attackers executing forged cross-site POST/PUT/DELETE requests or hijacking admin authentication maps. | * Cryptographic CSRF token generation and validation verified natively via `Zero\Support\Security::csrfToken()` and `Zero\Support\Security::csrfVerify()` (inside `src/Support/Security.php`).<br>* Globally enforced on all state-changing requests by `Zero\Http\Middleware\CsrfMiddleware` (inside `src/Http/Middleware/CsrfMiddleware.php`).<br>* Existing sessions and cached tokens are cleanly wiped on GET error fallbacks inside `LoginController::handle()` (inside `src/Modules/Admin/Controllers/LoginController.php`) to prevent cross-site leaks. |
| **T**ampering | **SQL Injection (SQLi), Cross-Site Scripting (XSS), & Parameter Tampering:** Injecting malformed query variables, persistent script blocks, or un-declared posted form fields. | * **SQLi Prevention:** Raw prepared statement parameter bindings enforced globally via the central database transceiver `Zero\Database\DB::query()` (inside `src/Database/DB.php`), guaranteeing 100% prepared statement execution. Identifiers that can't be parameter-bound (table/column names interpolated into cascade-delete SQL) are constrained to `^[a-zA-Z0-9_]+$` before use in `ModelApiController` (inside `src/Modules/Admin/Controllers/Api/ModelApiController.php`).<br>* **XSS Mitigation:** Public payloads cleaned recursively on boot via `Zero\Support\Security::sanitizeInput()` and advanced interactive block text rendering validated by the raw HTML sanitizer `Zero\Support\Security::sanitizeHtml()` (inside `src/Support/Security.php`), stripping malformed characters, dangerous script tags, and `javascript:` / `data:` protocols.<br>* **Parameter Tampering Prevention:** Form and model data verified against declarative schemas compiled by the core `Zero\Core\Validator` engine (inside `src/Core/Validator.php`), which strictly filters out non-declared fields using `$validator->getValidatedData()` before database writes. |
| **R**epudiation | **Audit Trail Failures:** Privileged administrators or rogue users executing state-changing operations (e.g. deleting pages, modifying variant stock) without persistent, un-mutilated audit logs. | * Dynamic, non-repudiable audit logging handled globally by `Zero\Support\Logger::log()` (inside `src/Support/Logger.php`), which records the triggering user's UUID, the specific action category, the targeted table, the target record ID, and serializes metadata (including the caller's IP address) as structured JSON directly into the persistent `audit_logs` database table. |
| **I**nformation Disclosure | **Multi-Tenant Data Leaks:** Rogue site tenants or external crawlers attempting to query, modify, or leak sensitive records (categories, pages, media, orders) belonging to other brands. | * Zero-trust multi-tenant isolation enforced natively by the ActiveRecord trait `Zero\Models\Traits\IsModel` (inside `src/Models/Traits/IsModel.php`).<br>* All read, save, and soft-delete statements (specifically `IsModel::all()` and `IsModel::save()`) automatically append active tenant scoping filters (`site_id = ?` based on `App::getCurrentSiteId()`), completely blocking cross-tenant boundaries on the database level. |
| **D**enial of Service | **Botnet Portal Flooding & Login Brute-Forcing:** Automated scripts flooding contact gateways with spam, brute-forcing back-office logins, or exhausting DB connections. | * **Spam Mitigation:** Forms utilize zero-friction hidden input fields named `website_url` styled with invisible classes (e.g. `.website-field-wrapper` inside `assets/css/blocks/form_builder.css`). Automated spambots are completely fooled into populating this decoy field. If populated, `FormApiController` (inside `src/Modules/FormBuilder/Controllers/FormApiController.php`) silently drops the submission without DB writes.<br>* **Brute-Force Throttling:** Strict rate-limiting enforced globally via `Zero\Http\Middleware\RateLimitMiddleware` (inside `src/Http/Middleware/RateLimitMiddleware.php`) using custom sliding-window limits and a `429` + `Retry-After` response. Failed admin logins are further throttled with an exact 1-second `sleep(1)` delay inside `LoginController` (inside `src/Modules/Admin/Controllers/LoginController.php`); the password-reset flow in `ForgotController` (inside `src/Modules/Admin/Controllers/ForgotController.php`) uses a lighter 250ms `usleep(250000)` delay to reduce (not eliminate) timing analysis leakage. |
| **E**levation of Privilege | **Administrative Role Bypass:** Normal editors or guest visitor sessions attempting to execute restricted administrative commands or modify other user profiles. | * Absolute boundary protection enforced via a dynamic declarative role verification pipeline (`App::applyRoleMiddleware('super_admin')` / `App::applyAuthMiddleware()`), evaluated natively inside individual back-office controllers (such as `ModelApiController.php` inside `src/Modules/Admin/Controllers/Api/ModelApiController.php`, which extends the shared `AdminApiControllerBase`) to block non-super_admin sessions before data execution. |

---

## 6. Automated Testing & Continuous Integration

Zero CMS maintains absolute stability and multi-tenant isolation via a **Zero-Dependency Automated Testing Suite**, with tests housed alongside the code they cover in each component's own `Tests/` folder (e.g. `src/Core/Tests/`, `src/Modules/Search/Tests/`), sharing one common `src/Support/TestBootstrap.php`:

* **psr-4 Autoloading:** `src/Support/TestBootstrap.php` boots CLI-safe environments and loads namespaces dynamically.
* **Subprocess Isolation:** `bin/test` (backed by `Zero\Support\TestRunner`) scans for suites matching `*Test.php` and executes several **concurrently**, each inside its own **isolated PHP subprocess** (`proc_open`, with a small pool of `TEST_TOKEN` slots giving each subprocess its own isolated database), preventing static variables, mock session variables, and transactional database connections from bleeding between tests.

### Execution
Run the automated unit testing suite inside the container before staging or deploying code:
```bash
docker exec -w /data/misc/zero php83 bin/test
```

### Automated Semantic Versioning & Releases

Every merge to `main` is automatically versioned and published — **no Node/npm, no third-party release tooling**, just two small zero-dependency PHP scripts under `bin/` and git/`gh` (GitHub's own CLI, already present on every Actions runner):

* **Commit convention:** Every commit subject must follow [Conventional Commits](https://www.conventionalcommits.org/): `type(scope): description`, e.g. `feat(auth): add OAuth login support` or `fix: correct off-by-one in pagination`. A `!` after the type/scope (`feat!: ...`) or a `BREAKING CHANGE:` footer marks a breaking change.
* **`.github/workflows/lint-commit-messages.yml`** runs `bin/check-commit-messages` on every pull request, failing fast if any commit's subject line doesn't match the convention above.
* **`.github/workflows/release.yml`** runs `bin/release` after `Run Automated Tests (CI)` succeeds on `main` — it never releases a commit the test suite hasn't already passed. It reads every commit since the last `v*` tag, computes the next version (`feat` → minor, `fix`/`perf` → patch, any breaking change → major), appends a `CHANGELOG.md` section, tags the release, and publishes it via `gh release create`.
* Run `bin/release --dry-run` at any time locally to preview the next version and changelog without tagging, committing, or publishing anything.
