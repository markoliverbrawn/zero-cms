# 🗺️ Zero CMS — Project Roadmap & Production Promotion Blueprint

This document outlines the system roadmap for Zero CMS, detailing past achievements, immediate staging/promotion goals, and the future development timeline for enterprise scaling and patching.

---

## 📅 Roadmap Overview

```
 [ Phase 1: Stabilisation ] ──> [ Phase 2: Sandbox & AI Media ] ──> [ Phase 3: Staging & CI/CD ] ──> [ Phase 4: Enterprise ]
 (100% SUCCESS State)           (Completed)                        (Next Milestones)               (Advanced Scaling)
```

---

## 🏛️ Phase 1: Zero-Dependency Core Stabilisation (Completed)
*Focused on eliminating package bloat, ensuring tenant isolation, and delivering a 100% offline, lightning-fast CMS.*

*   **Zero-Dependency AWS S3 Driver:** Implemented AWS Signature Version 4 (SigV4) request signing natively without requiring the official AWS SDK, reducing vendor overhead.
*   **Subprocess Master Test Runner:** Created a colorized, isolated PHP subprocess runner (`tests/run.php`) to test dynamic ORM traits and multi-tenant scoping without connection leaks. (Status: **36 / 36 passing test suites**).
*   **Pure MySQL Migration Engine:** Removed all legacy SQLite translation layers to execute high-performance, raw MySQL queries.
*   **Dynamic Layout Block Builder:** Restored the core block page-builder editor variables (`$blockBuilderField`, `$usesBlockBuilder`) to support rich content layouts.
*   **Guide Site Seed Correction:** Patched `seeders/data/documentation.json` media records with correct domains (`d6laptop.zero.guide`) to guarantee correct site-isolated paths.

---

## ⚙️ Phase 2: AI Media Assets & On-Demand Interactive Sandboxing (Completed)
*Focused on implementing high-fidelity AI-generated assets, interactive serverless deployment guides, and hot-pluggable multi-tenant sandbox demo generation.*

*   **Zero-Dependency AI Image Generator:** Developed `seeders/generate_ai_images.php` to query Google's **Imagen 4.0** API sequentially via our native `AiService`, generating 8 wide landscape (`16:9`) featured JPEGs mapped to the Guide theme's body gradients (completely purging legacy SVG placeholders!).
*   **Deterministic Blog Article Alignment:** Refactored `seeders/generate_blog_articles.php` to map these JPEGs to the 10 handwritten publications on the Guide site using a deterministic, self-documenting slug-to-filename lookup map.
*   **Low-Cost Serverless Setup Guide:** Created an in-depth, copy-pasteable CLI guide (`gcloud` commands) under slug `docs/how-tos/deploy-cloud-run`, explaining stateless scale-to-zero compute configs, db-f1-micro Cloud SQL MySQL databases over Unix domain sockets, and Cloud Run Jobs execution pipelines.
*   **CSP-Compliant Link Redirections:** Replaced legacy inline JavaScript `onclick` reload handlers with modern, CSP-compliant, and styled anchor tags on both the **Deploy** and **Spin Up Another Demo** buttons, neutralizing browser-level XSS blocking.
*   **On-Demand Sandbox Demo Generator:** Engineered a pluggable module (`src/Modules/DemoGenerator/`) with a transaction-safe controller that seeds isolated multi-tenant workspaces on-the-fly and dispatches credentials via raw SMTP sockets.
*   **In-Memory ID Translation Map & Path-Rewriting:** Solved database duplicate key collisions on consecutive demo creations by generating globally unique media IDs and developing a translation engine to walk and search-replace block-level ID mappings dynamically.
*   **Directory Cleanup Overrides:** Overrode `forceDelete()` in the core `Site` model to recursively delete empty parent folders `/public/storage/uploads/{siteId}/` once child assets are purged, securing 100% clean teardowns.
*   **Automated Lifecycle Verification Tests:** Created a lifecycle-verifying integration test suite (`tests/DemoGeneratorTest.php`), raising our total test count to **36 / 36 fully passing suites**!

---

## 🚀 Phase 3: CI/CD Pipeline & Automated PR Gatekeeping (Next Steps)
*Aimed at ensuring no broken code can reach any environment.*

### 3.1 Continuous Integration (CI) Runner (Completed)
Set up a sterile, containerized environment in GitHub Actions (`.github/workflows/run-tests.yml`) using a live MySQL service container that executes before any PR is allowed to merge.
*   **Gatekeeping Rule:** Any failed assertion in the 36 test suites blocks the pipeline automatically.

### 3.2 Backward-Compatible Migration Protocol
Create a strict developer checklist for database updates to ensure older active containers do not crash while rolling updates are deployed:
*   **Constraint 1:** Columns are **never** renamed or dropped in a single deployment.
*   **Constraint 2:** Use **Dual-State Rollout** (Add nullable column $\rightarrow$ Deploy code to write to both $\rightarrow$ Backfill data $\rightarrow$ Shift reads $\rightarrow$ Drop old column).

### 3.3 Modular CSS Bundle Optimization & Developer Workflows (Completed)
Improved front-office asset management and theme developer experience without introducing heavy third-party build tools (like Vite, Webpack, or PostCSS):
*   **Modular Stylesheet Splitting:** Refactored large monolithic theme files (such as `guide.css`) into smaller, domain-specific modular stylesheets inside their corresponding nested theme subdirectories.
*   **Dynamic Server-Side Compilation:** Registered the stylesheets inside the dynamic `CssBundleController.php` load sequence. This compiles, concatenates, and minifies them into a single, high-performance inlined CSS bundle (`main-*.css`) on the server-side—completely avoiding spec-violating browser `@import` statements and redundant, render-blocking HTTP latency.

---

## 📅 Phase 4: Blue/Green Serverless Deployments & Patching (Staging to Prod)
*Eliminating deployment downtime and establishing standard rollbacks.*

### 4.1 Immutable Container Swaps
Deploy all code as immutable Docker container revisions to Google Cloud Run:
*   **Zero Traffic Deployment:** Deploy new revisions with `0%` traffic.
*   **Isolated Verification:** Use the unique, revision-specific URL to verify that database migrations completed and pages render correctly in isolation.
*   **Atomic Split Shift:** Shift `100%` of live production traffic to the new revision instantaneously with no request downtime.

### 4.2 Secure Pre-Deployment Backups
Implement automated, compressed MySQL dumps stored directly inside your private Cloud Run storage buckets prior to initiating any deployment steps:
*   **Dumping Script:** `mysqldump --single-transaction -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | gzip > /backups/db-$(date +%F-%T).sql.gz`

### 4.3 Rapid Disaster Recovery
Enforce a 1-second fallback command blueprint:
*   **Rollback Command:** `gcloud run services update-traffic zerocms-service --to-revisions=stable-revision-id=100`

---

## ⚡ Phase 5: Advanced Enterprise Scaling
*Preparing Zero CMS for multi-tenant massive load environments.*

### 5.1 Global CDN Caching & Header Guards
*   Integrate Cloudflare or GCP Cloud CDN for whitelisted public asset routes (`/assets/` and `/storage/`).
*   Optimize HTTP header delivery (auto-injecting modern `Content-Security-Policy` and HTTP `Cache-Control` max-age limits for static SVGs).

### 5.2 Dynamic API Rate Limiting per Tenant
*   Implement a lightweight, memory-efficient rate limiter utilizing redis or db-level tokens to throttle excessive dynamic module requests (such as Forum posts or Contact submissions) on a per-tenant, per-IP basis.

### 5.3 Syslog Telemetry Export
*   Expose structured JSON logging formats from `src/Support/Logger.php` to stream real-time audit trails and security auditing telemetry directly to GCP Cloud Logging or external syslog hooks for unified threat analysis.
