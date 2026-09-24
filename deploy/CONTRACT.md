# Zero CMS Deployment Contract

This is what every provider toolkit under `deploy/<provider>/` must deliver to the app. It covers
the app's requirements only, not how a cloud meets them. A toolkit is correct if it meets every
**MUST** below. How it does so (Cloud Run or ECS, Cloud Scheduler or EventBridge) is up to that
toolkit alone.

Everything here comes from the app code; the source is cited in each case. If the code and this
document disagree, the code wins. Fix this document in the same change.

## 1. Steps

Each provider implements the same steps, one script per step, in this order:

| Step        | Delivers                                                                 |
|-------------|--------------------------------------------------------------------------|
| `database`  | A reachable MySQL 8 database; exports the `DB_*` values in §3.1          |
| `storage`   | An object store for uploads; exports the `STORAGE_*` values in §3.2      |
| `service`   | The web workload running the shared image (§2), plus the one-off jobs (§5) |
| `scheduler` | The two recurring HTTP triggers (§4)                                     |
| `domains`   | Optional. Custom-domain routing to the web workload                      |

A step's only output is the environment variables it exports for the steps after it. A later step
MUST NOT check which option an earlier step used (for example, `service` never asks whether the
database is Cloud SQL or Aiven). Options within a step live under `deploy/<provider>/<step>/`, for
example `deploy/gcp/db/cloudsql.sh` and `deploy/gcp/db/aiven.sh`.

Every step MUST be idempotent: re-running it against an existing deployment updates it in place.

## 2. The image

All providers run the same image, built from `deploy/image/`. No provider-specific code goes in
the image. The image provides:

- PHP 8.1+ (built on `php:8.3-apache`) with `pdo_mysql`, `gd` (with WebP, required by
  `Assets::supportsWebp()` in `src/Support/Assets.php`), `exif` and `bcmath`.
- The document root at `public/`, with `mod_rewrite` and `.htaccess` enabled.
- Listening on `$PORT`.
- Writable paths for the web user: `storage/`, `public/storage/` and `public/assets/css/cache/`.
  The queue endpoint's rate-limit lock (`storage/queue-web-lock.txt`) is written here, so on a
  platform with a read-only root filesystem these paths MUST be mounted writable.

The platform MUST deliver runtime configuration as process environment variables. `Env::get()`
(`src/Core/Env.php`) reads `getenv()` first, and Apache under mod_php passes the container
environment through. The entrypoint's `.env` copy is only a fallback, for SAPIs that clear the
environment.

## 3. Runtime environment

### 3.1 Database (`src/Database/DB.php`)

| Variable    | Required     | Notes                                                        |
|-------------|--------------|--------------------------------------------------------------|
| `DB_NAME`   | MUST         |                                                              |
| `DB_USER`   | MUST         |                                                              |
| `DB_PASS`   | MUST         | Restricted to `[a-zA-Z0-9_.-]`, so it passes safely through CLI env-var flags |
| `DB_SOCKET` | one of these | Unix socket path. When non-empty it takes precedence over host/port. Set it to empty when switching to TCP |
| `DB_HOST`   | one of these | Default `127.0.0.1`                                          |
| `DB_PORT`   | MAY          | Default `3306`                                               |
| `DB_SSL_CA` | MAY          | Path *inside the container* to a CA bundle. Enables verified TLS. The provider mounts the file (for example from a secret manager) |

### 3.2 Object storage (`src/Core/Storage/Storage.php`)

`STORAGE_DRIVER` MUST be `gcs` or `s3` in any multi-instance deployment. `local` writes to the
instance's own disk and loses uploads when an instance is replaced.

| Driver | Variables                                                                                   |
|--------|---------------------------------------------------------------------------------------------|
| `gcs`  | `GCS_BUCKET_NAME` (falls back to `GCS_BUCKET`); `GCS_KEY_FILE` MAY be set, otherwise the platform's workload identity is used; `GCS_PREDEFINED_ACL` MAY be set |
| `s3`   | `AWS_S3_BUCKET`, `AWS_DEFAULT_REGION` (default `us-east-1`), `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` (`src/Core/Storage/AwsS3StorageDriver.php`) |

### 3.3 Application

| Variable                  | Required | Notes                                                        |
|---------------------------|----------|--------------------------------------------------------------|
| `ENVIRONMENT`             | MUST     | `production` in deployed environments. Defaults to `production` if unset |
| `BASE_URL`                | MUST     | Public base URL. Used by the seeders and to derive the fallback signing key |
| `APP_KEY`                 | SHOULD   | Signs image-variant URLs (`Assets.php`). If unset, the key is derived from DB credentials and `BASE_URL`, so it changes whenever they change and breaks variant URLs in pages already open. MUST stay the same across deploys |
| `QUEUE_TRIGGER_TOKEN`     | MUST     | See §4                                                       |
| `SCHEDULER_TRIGGER_TOKEN` | MUST     | See §4                                                       |
| `TRUSTED_PROXY_SECRET`    | SHOULD   | See §4.1. When unset, `X-Forwarded-Host` is trusted from anyone (`Security::resolveTrustedHost()`) |
| `BENCHMARKING`            | MAY      | `true` turns on render timing; set it to `false` in production |
| `ADMIN_EMAIL`             | SHOULD   | Recipient for security audit reports; also applied to the admin account at seed time |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` | SHOULD | Without these, no mail is sent (password resets, audit reports) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | MAY | Google SSO |
| `AI_PROVIDER`, `GEMINI_API_KEY` | MAY | AI features. `AI_PROVIDER` defaults to `gemini` |
| `SEARCH_DRIVER`           | MAY      | Default `database`                                           |
| `SECURITY_AUDIT_SCHEDULE` | MAY      | Default `daily`                                              |

Secrets a toolkit generates (`DB_PASS`, the trigger tokens, `TRUSTED_PROXY_SECRET`, `APP_KEY`)
MUST be saved between runs so a redeploy doesn't rotate them. The current GCP toolkit saves them
in a gitignored, mode-`600` settings file.

## 4. Recurring triggers

There is no long-running worker. The scheduler calls into the web workload over HTTP:

| Trigger   | Request                                          | Auth                                                                 | Cadence     |
|-----------|--------------------------------------------------|----------------------------------------------------------------------|-------------|
| Queue     | `POST /api/v1/queue/process`                     | `?token=` or `X-Queue-Token` header matching `QUEUE_TRIGGER_TOKEN`         | every 5 min |
| Scheduler | `POST /api/v1/queue/schedule`                    | `?token=` or `X-Scheduler-Token` header matching `SCHEDULER_TRIGGER_TOKEN` | every 5 min |

Sources: `src/Modules/Queue/Controllers/QueueApiController.php` and `SchedulerApiController.php`.
Both return `405` for anything other than `POST`, `403` for a bad token, `500` if the token isn't
configured, and `429` if called again within 5 seconds.

Timing:

- The queue endpoint processes pending jobs for up to **800 s** per call
  (`QueueManager::runPendingJobs()`).
- The web workload's request timeout MUST be **at least 900 s**. That matches QueueManager's 900 s
  stale-lock window, so a job killed by a timeout can be picked up again immediately.
- The scheduler's own deadline for the queue trigger MUST be **longer than 800 s and shorter than
  the request timeout**. The GCP toolkit uses 820 s. Many schedulers cancel the request when their
  deadline passes (Cloud Scheduler's default is 180 s), which would cut off the job in progress.

### 4.1 Tenant resolution for trigger calls

The app picks the tenant by exact match of the request host against `sites.domain`, before any
route runs. A trigger sent to a platform-generated URL (such as `*.run.app` or an ALB hostname)
matches no site and gets the site-not-found page. The queue itself is global: any one real
domain resolves it for every tenant. So each trigger MUST either:

- call a real site domain directly, or
- call the platform URL with `X-Forwarded-Host: <a real sites.domain>` and
  `X-Proxy-Secret: <TRUSTED_PROXY_SECRET>` (checked by `Security::isTrustedProxyRequest()`).

## 5. One-off jobs

These run the same image with a different command and the same environment as the web workload:

| Job     | Command        | When                                                                |
|---------|----------------|---------------------------------------------------------------------|
| Migrate | `php bin/migrate` | Every deploy, before traffic moves to the new version. Only applies migrations that haven't run yet |
| Seed    | `php bin/seed` | Only on request. `SEED_SITES` limits which sites it touches (`SeederRunner`). The seeded `admin` account is renamed to `ADMIN_USER` if set, its password comes from `ADMIN_PASS` (legacy name `ADMIN_PASSWORD` is also accepted) and its email from `ADMIN_EMAIL`, re-applied on every seed run (`src/Support/Seeder.php`) |

## 6. Project extensions

A host project (for example zero-mobsites) MUST NOT edit shared toolkit files. It provides:

- a settings file with its own values (names, region, domains);
- extra environment variables for its own code, passed through an `EXTRA_ENV_VARS` hook;
- extra scripts alongside the toolkit, never patched into it.

## Known gaps in the current GCP toolkit

- **No proxy headers on triggers.** Core's `cloud_scheduler_setup.sh` doesn't send the §4.1
  headers or set the §4 scheduler deadline. zero-mobsites' copy does both.
