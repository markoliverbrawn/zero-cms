# 🗺️ Zero CMS — Project Roadmap & Production Promotion Blueprint

This document outlines the system roadmap for Zero CMS, detailing past achievements, immediate staging/promotion goals, and the future development timeline for enterprise scaling and patching.

---

## 📅 Roadmap Overview

```
 [ Phase 1: Stabilisation ] ──> [ Phase 2: Staging & CI/CD ] ──> [ Phase 3: Zero-Downtime ] ──> [ Phase 4: Enterprise ]
 (100% SUCCESS State)           (Next Milestones)               (Automated Patching)         (Advanced Scaling)
```

---

## 🏛️ Phase 1: Zero-Dependency Core Stabilisation (Completed)
*Focused on eliminating package bloat, ensuring tenant isolation, and delivering a 100% offline, lightning-fast CMS.*

*   **Zero-Dependency AWS S3 Driver:** Implemented AWS Signature Version 4 (SigV4) request signing natively without requiring the official AWS SDK, reducing vendor overhead.
*   **Subprocess Master Test Runner:** Created a colorized, isolated PHP subprocess runner (`tests/run.php`) to test dynamic ORM traits and multi-tenant scoping without connection leaks. (Status: **31 / 31 passing test suites**).
*   **Pure MySQL Migration Engine:** Removed all legacy SQLite translation layers to execute high-performance, raw MySQL queries.
*   **Dynamic Layout Block Builder:** Restored the core block page-builder editor variables (`$blockBuilderField`, `$usesBlockBuilder`) to support rich content layouts.
*   **Guide Site Seed Correction:** Patched `seeders/data/documentation.json` media records with correct domains (`d6laptop.zero.guide`) to guarantee correct site-isolated paths.

---

## 🚀 Phase 2: CI/CD Pipeline & Automated PR Gatekeeping (Next Steps)
*Aimed at ensuring no broken code can reach any environment.*

### 2.1 Continuous Integration (CI) Runner
Set up a sterile, containerized environment in GitHub Actions, GitLab CI, or local Gitea Actions that executes before any PR is allowed to merge.
*   **Actionable Task:** Create `.github/workflows/ci.yml` using a live MySQL service container.
*   **Gatekeeping Rule:** Any failed assertion in the 31 test suites blocks the pipeline automatically.

### 2.2 Backward-Compatible Migration Protocol
Create a strict developer checklist for database updates to ensure older active containers do not crash while rolling updates are deployed:
*   **Constraint 1:** Columns are **never** renamed or dropped in a single deployment.
*   **Constraint 2:** Use **Dual-State Rollout** (Add nullable column $\rightarrow$ Deploy code to write to both $\rightarrow$ Backfill data $\rightarrow$ Shift reads $\rightarrow$ Drop old column).

---

## 🟢 Phase 3: Blue/Green Serverless Deployments & Patching (Staging to Prod)
*Eliminating deployment downtime and establishing standard rollbacks.*

### 3.1 Immutable Container Swaps
Deploy all code as immutable Docker container revisions to Google Cloud Run:
*   **Zero Traffic Deployment:** Deploy new revisions with `0%` traffic.
*   **Isolated Verification:** Use the unique, revision-specific URL to verify that database migrations completed and pages render correctly in isolation.
*   **Atomic Split Shift:** Shift `100%` of live production traffic to the new revision instantaneously with no request downtime.

### 3.2 Secure Pre-Deployment Backups
Implement automated, compressed MySQL dumps stored directly inside your private Cloud Run storage buckets prior to initiating any deployment steps:
*   **Dumping Script:** `mysqldump --single-transaction -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | gzip > /backups/db-$(date +%F-%T).sql.gz`

### 3.3 Rapid Disaster Recovery
Enforce a 1-second fallback command blueprint:
*   **Rollback Command:** `gcloud run services update-traffic zerocms-service --to-revisions=stable-revision-id=100`

---

## ⚡ Phase 4: Advanced Enterprise Scaling
*Preparing Zero CMS for multi-tenant massive load environments.*

### 4.1 Global CDN Caching & Header Guards
*   Integrate Cloudflare or GCP Cloud CDN for whitelisted public asset routes (`/assets/` and `/storage/`).
*   Optimize HTTP header delivery (auto-injecting modern `Content-Security-Policy` and HTTP `Cache-Control` max-age limits for static SVGs).

### 4.2 Dynamic API Rate Limiting per Tenant
*   Implement a lightweight, memory-efficient rate limiter utilizing redis or db-level tokens to throttle excessive dynamic module requests (such as Forum posts or Contact submissions) on a per-tenant, per-IP basis.

### 4.3 Syslog Telemetry Export
*   Expose structured JSON logging formats from `src/Support/Logger.php` to stream real-time audit trails and security auditing telemetry directly to GCP Cloud Logging or external syslog hooks for unified threat analysis.
