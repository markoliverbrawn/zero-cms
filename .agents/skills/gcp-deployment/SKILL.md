---
name: gcp-deployment
description: Deploys Zero CMS to Google Cloud (Cloud Run + Cloud SQL + Cloud Storage + Cloud Scheduler) using the deploy/gcp/ toolkit. Interactively asks whether to provision a new Cloud SQL instance and Storage bucket (vs. reusing existing ones) and whether to run migrations, then builds/pushes the image, deploys the web service, the migration/seed jobs, and the scheduler cron triggers. Use when the user asks to deploy to GCP, ship a release to Cloud Run, or stand up Google Cloud infrastructure for a Zero CMS site.
---

# Deploying Zero CMS to Google Cloud

This drives the scripts under `deploy/gcp/` to take a Zero CMS checkout from zero to a fully
running, scale-to-zero site on Google Cloud. Read this whole skill before running anything — several
steps are billable and one (`RUN_SEED`) is destructive.

## Architecture this provisions

- **Cloud Run service** (`<DEPLOYMENT_NAME>-service`) — the stateless web app (Apache + PHP 8.3),
  `--allow-unauthenticated`, connected to Cloud SQL over a Unix socket.
- **Cloud SQL** (`db-f1-micro` MySQL 8.0) — the database. Connected via `--set-cloudsql-instances`,
  never a public IP.
- **Cloud Storage buckets** — a public, uniform-access one for media uploads when `STORAGE_DRIVER=gcs`,
  and a private one (public access prevention enforced) for `storage/private/` files, served only
  through signed URLs. `project.sh` grants the runtime service account
  `roles/iam.serviceAccountTokenCreator` on itself so it can sign them; if the deployer can't, it
  prints the command to run once by hand.
- **Two Cloud Run Jobs** — `<DEPLOYMENT_NAME>-migrate-job` (safe, up-only schema migrations via
  `bin/migrate`) and `<DEPLOYMENT_NAME>-seed-job` (destructive multi-tenant reseed via `bin/seed`,
  only created/run when explicitly requested).
- **Two Cloud Scheduler HTTP jobs**, every 5 minutes, hitting the *already-deployed* Cloud Run
  service (not a separate always-on worker — this is what keeps the whole thing scale-to-zero):
  `POST /api/v1/queue/process?token=...` (job queue) and `POST /api/v1/queue/schedule?token=...`
  (recurring tasks, see `Zero\Modules\Queue\Support\Scheduler`). Both are token-gated by
  `QUEUE_TRIGGER_TOKEN` / `SCHEDULER_TRIGGER_TOKEN`.
- **Custom domain mappings** (optional, off by default) — `gcloud beta run domain-mappings` entries
  binding one or more external domains to the Cloud Run service, so a client's own domain (CNAMEd or
  A/AAAA-recorded at Google) serves the same deployment as the default `*.run.app` URL.

All of this is orchestrated by `deploy/gcp/setup.sh`, which sources `config.sh` (settings, secret
generation, validation, and the database option chosen by `DB_PROVIDER`) then runs `project.sh` →
`storage.sh` → `database.sh` → `service.sh` → `scheduler.sh` → `domains.sh` in order. Every step is
idempotent — re-running the whole pipeline on an existing deployment updates it in place rather than
duplicating resources. The layout and the rules that keep it provider-neutral are in
`deploy/CONTRACT.md`; the image files shared by every provider are in `deploy/image/`.

## Prerequisites (verify before starting)

- `gcloud` CLI installed and the user has (or can get) an authenticated account with billing enabled
  on the target project.
- `docker` installed if building locally (`USE_LOCAL_DOCKER=true`, the default) — otherwise Cloud
  Build compiles remotely and no local Docker is needed.
- Run all commands from the repository root (the scripts refuse to run otherwise — they check for
  `public/index.php`).

## Step 1 — Resolve the target project

Run `gcloud config get-value project`. If unset or the user hasn't confirmed it, ask which GCP
project to deploy into before doing anything billable.

## Step 2 — Ask the three questions this skill exists to ask

Use `AskUserQuestion` (do not just assume defaults for these three — they're either billable,
hard-to-reverse, or destructive):

1. **Cloud SQL** — "Create a new Cloud SQL instance for this deployment, or connect to an existing
   one?"
   - *Create new* (recommended for a first deploy) → `CREATE_CLOUDSQL=true`. `database.sh`
     (via `db/cloudsql.sh`) provisions a `db-f1-micro` instance, database, and user if they don't already exist.
   - *Use existing* → `CREATE_CLOUDSQL=false`, and ask for the existing `CLOUDSQL_INSTANCE` name
     (plus `DB_NAME`/`DB_USER`/`DB_PASS` if they differ from the persisted `.deploy/gcp.secrets.env`
     values). The setup step then skips provisioning entirely and trusts that instance/db/user
     already exist.
   - *External Aiven MySQL* (only if the user brings one up) → `DB_PROVIDER=aiven` plus
     `AIVEN_CONNECTION_STRING`, and the one-off CA secret described in `deploy/gcp/db/aiven.sh`'s
     header. Nothing is provisioned on GCP for the database in this mode.

2. **Cloud Storage bucket** — "Create a new public GCS bucket for media, or use an existing bucket?"
   - *Create new* → `CREATE_STORAGE_BUCKET=true` (default). `storage.sh` creates a
     uniform-access bucket and grants `roles/storage.objectViewer` to `allUsers`.
   - *Use existing* → `CREATE_STORAGE_BUCKET=false`, ask for the existing `GCS_BUCKET_NAME`. Note
     that the skip path does *not* touch the existing bucket's IAM policy — mention this so the user
     can confirm public read access is already configured the way they want it.

3. **Migrations** — "Run database schema migrations as part of this deploy?"
   - *Yes* (default, safe/up-only, recommended) → `RUN_MIGRATIONS=true`.
   - *No* (e.g. schema already migrated elsewhere, or intentionally deferring) → `RUN_MIGRATIONS=false`.

   Separately — **never** default `RUN_SEED=true` without an explicit, unambiguous ask from the
   user. It wipes every table and reseeds a fresh multi-tenant kitchen-sink dataset. If the user
   mentions seeding, confirm out loud that they understand it destroys existing data before setting
   `RUN_SEED=true`.

## Step 2b — Custom domain mapping (optional, only if the user wants one)

Ask only if the user mentions wanting a custom/client domain to point at this deployment (don't
volunteer it unprompted for a first deploy): "Which domain(s) should be mapped onto this deployment?"
→ set `DOMAIN_MAPPINGS` to a comma-separated list (e.g. `www.client-a.com,client-b.com`).

Before running the pipeline with `DOMAIN_MAPPINGS` set, tell the user:
- Each domain must be verified as owned by the GCP account in
  [Search Console](https://search.google.com/search-console/ownership) *before* the script runs, or
  `gcloud beta run domain-mappings create` fails outright. Verify the apex (`client-a.com`), not just
  the `www` subdomain — verification is per registrable domain.
- Domain mappings only work in a subset of Cloud Run regions (`config.sh`'s
  `DOMAIN_MAPPING_SUPPORTED_REGIONS` — currently `asia-east1`, `asia-northeast1`, `asia-southeast1`,
  `europe-north1`, `europe-west1`, `europe-west4`, `us-central1`, `us-east1`, `us-east4`, `us-west1`).
  **This skill's own documented default region, `australia-southeast1`, is not one of them.**
  `config.sh` fails fast with a clear error at the very start of the pipeline if `GCP_REGION` is
  unsupported and `DOMAIN_MAPPINGS` is set — before any billable provisioning happens — but since a
  Cloud Run service's region can't be changed after creation, this needs deciding *before* the first
  deploy, not discovered after. If the user wants domain mapping, set `GCP_REGION` to a supported
  region from the start rather than defaulting to `australia-southeast1`.
- Domain mapping only wires up infrastructure/TLS. Zero CMS still resolves tenants by an **exact
  match** on the `sites.domain` column against the request's Host header
  (`src/Core/Concerns/ResolvesTenantContext.php`) — there's no wildcard/alias support. Each mapped
  domain must *also* be entered as that tenant's domain in Admin → Sites
  (`/admin/edit/sites/{id}`, list at `/admin/list/sites`), or requests to it 404 to the
  site-not-found page.
- Managed SSL cert issuance only starts once DNS actually resolves, and can take minutes to ~24
  hours.

## Step 3 — Confirm the resolved plan before touching the cloud

Echo a short summary (project, region, deployment name, the three answers above, and whether
`RUN_SEED` is set) and get explicit confirmation before running anything — this is billable cloud
infrastructure and Cloud SQL provisioning in particular takes several minutes and costs money to
leave running.

## Step 4 — Run the pipeline

Export the resolved flags and run the master script from the repo root:

```bash
export GCP_PROJECT_ID="..."          # only if not already the active gcloud project
export GCP_REGION="australia-southeast1"   # or whatever the user wants; see config.sh for default
export CREATE_CLOUDSQL=true|false
export CREATE_STORAGE_BUCKET=true|false
export RUN_MIGRATIONS=true|false
export RUN_SEED=false                # only true on explicit, confirmed request
export DOMAIN_MAPPINGS="www.client-a.com,client-b.com"  # optional, comma-separated, omit to skip
# If reusing existing infra, also export CLOUDSQL_INSTANCE / GCS_BUCKET_NAME / DB_NAME / DB_USER / DB_PASS

./deploy/gcp/setup.sh
```

Or run the sub-scripts individually (same order the master script uses) if only part of the pipeline
needs to re-run — e.g. `./deploy/gcp/service.sh` alone to ship a new image without touching
infrastructure, or `./deploy/gcp/scheduler.sh` alone to fix up the cron jobs after
the service URL changes.

`config.sh` resolves/generates `DB_PASS`, `ADMIN_PASS`, `QUEUE_TRIGGER_TOKEN`,
`SCHEDULER_TRIGGER_TOKEN`, `TRUSTED_PROXY_SECRET` and `APP_KEY` once and persists them to `.deploy/gcp.secrets.env` in the project root
(gitignored, mode `600`) so re-deploys don't drift credentials. A legacy
`deployments/gcp/.env.gcp` is picked up and migrated automatically on the first run. Never print
these values into chat; point the user at that file if they need them.

## Step 5 — Verify

After the pipeline finishes:
- `gcloud run services describe <DEPLOYMENT_NAME>-service --region=<region> --format="value(status.url)"`
  and load it — confirm the site actually renders.
- `gcloud scheduler jobs list --location=<region>` — confirm both `<DEPLOYMENT_NAME>-queue-scheduler`
  and `<DEPLOYMENT_NAME>-task-scheduler` exist and their last run succeeded
  (`gcloud scheduler jobs describe <name> --location=<region>` shows `lastAttemptTime`/status).
- If migrations ran, `gcloud run jobs executions list --job=<DEPLOYMENT_NAME>-migrate-job
  --region=<region>` should show a successful execution.
- If `DOMAIN_MAPPINGS` was set, `gcloud beta run domain-mappings describe --domain=<domain>
  --region=<region>` for each domain — confirm `status.conditions` shows `Ready: True` (DNS resolved
  and managed cert issued; this can lag behind the script finishing). Then confirm the domain was
  also added in Admin → Sites (`/admin/edit/sites/{id}`) — otherwise the domain resolves and serves
  TLS fine but the app 404s it as an unrecognized tenant.

## Flags reference (see `config.sh` and `db/*.sh`)

Set via env var, or as `KEY=value` lines in the project's settings file (`.deploy/gcp.env` by
default, overridable with `DEPLOY_SETTINGS_FILE`). An env var always wins over the file.

| Variable | Default | Meaning |
|---|---|---|
| `GCP_PROJECT_ID` | active `gcloud` project | Target project |
| `GCP_REGION` | `australia-southeast1` | Region for all resources |
| `DEPLOYMENT_NAME` | `zerocms` | Prefix for all service/job/scheduler names |
| `DB_PROVIDER` | `cloudsql` | Database option under `deploy/gcp/db/`: `cloudsql` or `aiven` |
| `CLOUDSQL_INSTANCE` | `zerocms-db` | Cloud SQL instance name |
| `GCS_BUCKET_NAME` | `zerocms-media-uploads` | Bucket name (globally unique) |
| `GCS_PRIVATE_BUCKET_NAME` | `<GCS_BUCKET_NAME>-private` | Bucket for `storage/private/` files (backups, restore uploads). Always created if missing, with public access prevention enforced; must differ from `GCS_BUCKET_NAME`. |
| `DB_NAME` / `DB_USER` | `zerocms_db` / `zerocms_db_user` | Database + user |
| `CREATE_CLOUDSQL` | `true` | Provision Cloud SQL, or reuse an existing instance |
| `CREATE_STORAGE_BUCKET` | `true` | Provision the bucket, or reuse an existing one |
| `RUN_MIGRATIONS` | `true` | Run the safe up-only migration job this deploy |
| `RUN_SEED` | `false` | **Destructive** — wipes and reseeds all data. Confirm explicitly. |
| `IMAGE_TAG` | `v1` | Container image tag |
| `USE_LOCAL_DOCKER` | `true` | Build locally + push, vs. remote Cloud Build |
| `RUNTIME_SERVICE_ACCOUNT` | *(derived)* | The account Cloud Run runs as, which gets `roles/iam.serviceAccountTokenCreator` on itself for signed URLs. Empty derives the Compute Engine default from the project number (needs the Cloud Resource Manager API). |
| `BUILD_SOURCE_RETENTION_DAYS` | `7` | Remote builds only: days to keep uploaded build sources in the staging bucket before a lifecycle rule deletes them. Only set on a bucket with no lifecycle policy. |
| `DOMAIN_MAPPINGS` | *(empty)* | Comma-separated custom domains to map onto the Cloud Run service. Empty = skip entirely. |
| `AIVEN_CONNECTION_STRING` / `AIVEN_CA_SECRET` | *(empty)* / `aiven-ca` | Aiven Service URI and CA secret name; only for `DB_PROVIDER=aiven` |
| `ADMIN_EMAIL` / `SMTP_HOST` / `SMTP_PORT` / `SMTP_SECURE` / `SMTP_USER` / `SMTP_PASS` / `SMTP_FROM_EMAIL` / `SMTP_FROM_NAME` | *(empty)* | Mail. Without `SMTP_HOST` the app sends no mail. Keep `SMTP_PASS` in the environment, not the settings file. |
| `SCHEDULER_TARGET_DOMAIN` | *(empty)* | A real `sites.domain` the scheduler triggers send as `X-Forwarded-Host`. Needed once the seeded default site no longer uses the `*.run.app` host. |
| `EXTRA_ENV_VARS` | *(empty)* | Extra comma-separated `KEY=value` pairs for the service and jobs (host-project hook) |

## Known constraints worth knowing before you touch these scripts

- This repo *is* Zero CMS Core and runs standalone (`public/index.php` at the repo root, per
  `docker-compose.yml`) — the shared Dockerfile (`deploy/image/Dockerfile`) mirrors that layout (`/var/www/html/public`, not a
  nested `zero/` subfolder). If you ever see `zero/` path prefixes reappear in these scripts,
  that's the bug this skill's setup fixed once already — don't reintroduce it.
- `deploy/image/entrypoint.sh` writes the container's runtime `.env` from a whitelist of env-var prefixes
  (`DB_`, `GCS_`, `STORAGE_`, `ENVIRONMENT`, `BASE_`, `ADMIN_`, plus `APP_KEY`,
  `QUEUE_TRIGGER_TOKEN`, `SCHEDULER_TRIGGER_TOKEN`, `TRUSTED_PROXY_SECRET`, `GOOGLE_`, `AWS_`,
  `SMTP_`). Apache (mod_php) does *not* scrub the container environment, and `Env::get()` checks
  `getenv()` before `.env`, so a Cloud Run env var reaches the app even if it's missing from the
  whitelist — verified live on zero-mobsites. Still add new runtime env vars to the whitelist as a
  safety net for a future move to a scrubbing SAPI (php-fpm with `clear_env=yes`).
- Host projects run the toolkit from `vendor/markoliverbrawn/zero-cms-core/deploy/gcp/` in their own
  root, after `composer install`; their settings, extra ignore rules and secrets live in their own
  `.deploy/` folder (see README section 7).
- The queue/scheduler Cloud Scheduler jobs both call back into the *web service*, not a separate
  worker — there is no long-running daemon in this deployment (`bin/queue-runner` /
  `bin/scheduler`'s daemon loop are for non-serverless hosts only, e.g. `docker-compose.yml`-style
  deployments). Don't try to deploy those as Cloud Run services; the HTTP trigger pattern already
  covers the same job.
