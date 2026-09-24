#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP TOOLKIT CONFIGURATION
# ==============================================================================
# Sourced by every deploy/gcp/*.sh step. Resolves settings in this order, first
# wins:
#   1. variables already set in the environment (CI variables, one-off overrides)
#   2. the project's settings file ($DEPLOY_SETTINGS_FILE, default .deploy/gcp.env)
#   3. the defaults below
# then loads/generates persisted secrets and the selected database option
# (deploy/gcp/db/$DB_PROVIDER.sh).
# ==============================================================================

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"

GCP_TOOLKIT_DIR="$DEPLOY_TOOLKIT_DIR/gcp"
export GCP_TOOLKIT_DIR

require_project_root

export DEPLOY_SETTINGS_FILE="${DEPLOY_SETTINGS_FILE:-$PROJECT_ROOT/.deploy/gcp.env}"
load_settings "$DEPLOY_SETTINGS_FILE"

# ------------------------------------------------------------------------------
# DEFAULT PARAMETER CONFIGURATION (Override via env vars or the settings file)
# ------------------------------------------------------------------------------
export GCP_PROJECT_ID="${GCP_PROJECT_ID:-}"  # Active GCP Project ID (resolved dynamically if empty)
export GCP_REGION="${GCP_REGION:-australia-southeast1}"      # GCP region for compute and storage
export GCS_BUCKET_NAME="${GCS_BUCKET_NAME:-zerocms-media-uploads}" # Globally unique GCS bucket name
export ADMIN_USER="${ADMIN_USER:-admin}"                     # Admin username for initial seeding
export ADMIN_PASS="${ADMIN_PASS:-}"           # Generate randomly or read from persistent file

export DEPLOYMENT_NAME="${DEPLOYMENT_NAME:-zerocms}"              # Base prefix name for services/jobs
export IMAGE_TAG="${IMAGE_TAG:-v1}"                         # Deployment revision tag
export USE_LOCAL_DOCKER="${USE_LOCAL_DOCKER:-true}"         # Build locally with Docker (recommended)

# Which database option under deploy/gcp/db/ provides the database: "cloudsql" (a Cloud SQL
# instance this toolkit provisions) or "aiven" (an external Aiven MySQL service).
export DB_PROVIDER="${DB_PROVIDER:-cloudsql}"

# Mail and admin contact (all optional). Without SMTP_*, the app sends no mail (password resets,
# security audit reports); ADMIN_EMAIL receives audit reports and is set on the admin account at
# seed time. Keep SMTP_PASS out of the committed settings file -- set it in CI or the environment.
export ADMIN_EMAIL="${ADMIN_EMAIL:-}"
export SMTP_HOST="${SMTP_HOST:-}"
export SMTP_PORT="${SMTP_PORT:-}"
export SMTP_SECURE="${SMTP_SECURE:-}"        # 'tls' enables STARTTLS (src/Support/Emailer.php)
export SMTP_USER="${SMTP_USER:-}"
export SMTP_PASS="${SMTP_PASS:-}"
export SMTP_FROM_EMAIL="${SMTP_FROM_EMAIL:-}"
export SMTP_FROM_NAME="${SMTP_FROM_NAME:-}"

# A real `sites.domain` the Cloud Scheduler triggers present as X-Forwarded-Host (with
# X-Proxy-Secret) when calling the *.run.app URL -- see scheduler.sh. Leave empty while the seeded
# default site's domain is still the *.run.app host (the seed job sets it from BASE_URL); set it
# once that site moves to a real domain, or the triggers hit the site-not-found page.
export SCHEDULER_TARGET_DOMAIN="${SCHEDULER_TARGET_DOMAIN:-}"

# Comma-separated custom domains to map onto the Cloud Run service (e.g. "www.client-a.com,client-b.com").
# Empty by default -- domains.sh is a no-op until this is set.
export DOMAIN_MAPPINGS="${DOMAIN_MAPPINGS:-}"

# ------------------------------------------------------------------------------
# PROVISIONING & MIGRATIONS CONTROLS
# ------------------------------------------------------------------------------
# By default, we provision a fresh GCS bucket. Set to false to instead reuse an existing bucket
# (GCS_BUCKET_NAME points at a bucket that already exists, possibly outside this project).
# The database option has its own equivalent (e.g. CREATE_CLOUDSQL in db/cloudsql.sh).
export CREATE_STORAGE_BUCKET="${CREATE_STORAGE_BUCKET:-true}"

# By default, we run safe database migrations (Schema up-only, no data loss) on every deploy.
# Set to false to skip the migration step for a given deploy run.
export RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"

# We DO NOT run database seeders (which wipe the DB and overwrite user data) unless explicitly requested.
export RUN_SEED="${RUN_SEED:-false}"

# Resource names derived from DEPLOYMENT_NAME, shared by every step that refers to them.
export SERVICE_NAME="$DEPLOYMENT_NAME-service"

# ------------------------------------------------------------------------------
# PERSISTENT CREDENTIALS
# ------------------------------------------------------------------------------
# Gitignored, mode 600. Kept in the project (not the toolkit directory), so it survives a
# `composer update` when the toolkit is installed under vendor/.
export GCP_SECRETS_FILE="${GCP_SECRETS_FILE:-$PROJECT_ROOT/.deploy/gcp.secrets.env}"
LEGACY_SECRETS_FILE="$PROJECT_ROOT/deployments/gcp/.env.gcp"
if [ ! -f "$GCP_SECRETS_FILE" ] && [ -f "$LEGACY_SECRETS_FILE" ]; then
    # One-time migration from the old deployments/gcp/ toolkit: reuse its credentials rather than
    # rotating every password and token, and write them to the new location below.
    log_warn "Migrating credentials from legacy $LEGACY_SECRETS_FILE to $GCP_SECRETS_FILE..."
    load_secrets "$LEGACY_SECRETS_FILE"
    SECRETS_CHANGED=true
else
    load_secrets "$GCP_SECRETS_FILE"
fi

ensure_secret ADMIN_PASS password
# QUEUE_TRIGGER_TOKEN/SCHEDULER_TRIGGER_TOKEN authorize Cloud Scheduler's HTTP calls into
# /api/v1/queue/process and /api/v1/queue/schedule (see QueueApiController/SchedulerApiController).
# Generated here (not lazily inside service.sh/scheduler.sh) so every script in the pipeline sees
# the same value on a given run and it lands in every --set-env-vars call up front.
ensure_secret QUEUE_TRIGGER_TOKEN token
ensure_secret SCHEDULER_TRIGGER_TOKEN token
# TRUSTED_PROXY_SECRET gates whether the app trusts X-Forwarded-Host (Security::resolveTrustedHost());
# unset, anyone can spoof the host the app resolves. APP_KEY signs image-variant URLs; unset, the
# app derives a key from DB credentials and BASE_URL, which changes whenever they do and breaks
# variant URLs in pages already open. Both must stay the same across deploys, so they're persisted.
ensure_secret TRUSTED_PROXY_SECRET token
ensure_secret APP_KEY token

# ------------------------------------------------------------------------------
# AUTOMATIC PROJECT ID RESOLUTION
# ------------------------------------------------------------------------------
if [ -z "$GCP_PROJECT_ID" ]; then
    log_info "No GCP_PROJECT_ID provided. Attempting to resolve dynamically from active gcloud configuration..."
    export GCP_PROJECT_ID=$(gcloud config get-value project 2>/dev/null || true)
    if [ -z "$GCP_PROJECT_ID" ] || [ "$GCP_PROJECT_ID" = "(unset)" ]; then
        log_error "Could not resolve active Google Cloud Project ID. Please configure it in the environment (export GCP_PROJECT_ID=\"your-project\")."
        exit 1
    fi
    log_success "Resolved GCP_PROJECT_ID dynamically: $GCP_PROJECT_ID"
fi

# ------------------------------------------------------------------------------
# INPUT VALIDATION & SHELL SANITIZATION
# ------------------------------------------------------------------------------
# Cloud Run domain mappings are only available in a subset of Cloud Run regions -- unlike Cloud
# SQL/Storage/Cloud Run itself, which work in any region. Source: "Domain mapping is available in
# the following regions" at https://cloud.google.com/run/docs/mapping-custom-domains (checked
# 2026-08-19). Hand-maintained, not queried dynamically -- gcloud has no "is this region supported"
# lookup, only a create-time failure -- so re-check that page if Google adds regions and this list
# goes stale.
DOMAIN_MAPPING_SUPPORTED_REGIONS=(
    asia-east1 asia-northeast1 asia-southeast1
    europe-north1 europe-west1 europe-west4
    us-central1 us-east1 us-east4 us-west1
)

validate_domain_mapping_region() {
    local region="$1"
    local supported
    for supported in "${DOMAIN_MAPPING_SUPPORTED_REGIONS[@]}"; do
        if [ "$region" = "$supported" ]; then
            return 0
        fi
    done
    log_error "GCP_REGION '$region' does not support Cloud Run domain mappings, but DOMAIN_MAPPINGS is set."
    log_error "Supported regions: ${DOMAIN_MAPPING_SUPPORTED_REGIONS[*]}"
    log_error "A Cloud Run service's region is fixed at creation -- deploying here first and trying"
    log_error "to map a domain onto it afterward will fail at that step regardless. Either set"
    log_error "GCP_REGION to one of the supported regions above, or unset DOMAIN_MAPPINGS."
    exit 1
}

log_info "Validating configuration parameters for security and shell integrity..."
validate_safe_string "$GCP_PROJECT_ID" "GCP_PROJECT_ID" '^[a-zA-Z0-9_.:-]+$' # Project ID can have domain, dots, or colons
validate_safe_string "$GCP_REGION" "GCP_REGION" '^[a-zA-Z0-9-]+$' # Regions typically only have letters/numbers/dashes
validate_safe_string "$GCS_BUCKET_NAME" "GCS_BUCKET_NAME"
validate_safe_string "$ADMIN_USER" "ADMIN_USER"
validate_safe_string "$ADMIN_PASS" "ADMIN_PASS"
validate_safe_string "$DEPLOYMENT_NAME" "DEPLOYMENT_NAME"
validate_safe_string "$DB_PROVIDER" "DB_PROVIDER"
validate_safe_string "$TRUSTED_PROXY_SECRET" "TRUSTED_PROXY_SECRET"
validate_safe_string "$APP_KEY" "APP_KEY"
EMAIL_PATTERN='^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+$'
HOSTNAME_PATTERN='^[a-zA-Z0-9.-]+$'
[ -n "$ADMIN_EMAIL" ] && validate_safe_string "$ADMIN_EMAIL" "ADMIN_EMAIL" "$EMAIL_PATTERN"
[ -n "$SMTP_FROM_EMAIL" ] && validate_safe_string "$SMTP_FROM_EMAIL" "SMTP_FROM_EMAIL" "$EMAIL_PATTERN"
[ -n "$SMTP_HOST" ] && validate_safe_string "$SMTP_HOST" "SMTP_HOST" "$HOSTNAME_PATTERN"
[ -n "$SMTP_PORT" ] && validate_safe_string "$SMTP_PORT" "SMTP_PORT" '^[0-9]+$'
[ -n "$SMTP_SECURE" ] && validate_safe_string "$SMTP_SECURE" "SMTP_SECURE" '^[a-z]+$'
[ -n "$SCHEDULER_TARGET_DOMAIN" ] && validate_safe_string "$SCHEDULER_TARGET_DOMAIN" "SCHEDULER_TARGET_DOMAIN" "$HOSTNAME_PATTERN"
validate_env_value "$SMTP_USER" "SMTP_USER"
validate_env_value "$SMTP_PASS" "SMTP_PASS"
validate_env_value "$SMTP_FROM_NAME" "SMTP_FROM_NAME"
# Warned once per pipeline run, not once per step (every step re-sources this file).
if [ -z "$SMTP_HOST" ] && [ -z "$ZERO_DEPLOY_SMTP_WARNED" ]; then
    log_warn "SMTP_HOST is not set -- the deployed app won't send mail (password resets, security audit reports)."
    export ZERO_DEPLOY_SMTP_WARNED=1
fi
if [ -n "$DOMAIN_MAPPINGS" ]; then
    validate_safe_string "$DOMAIN_MAPPINGS" "DOMAIN_MAPPINGS" '^[a-zA-Z0-9,.-]+$' # Comma-separated list of hostnames
    # Fails the whole pipeline here, at the very first script sourcing config.sh, rather than only
    # at the domain-mapping step itself (the last step setup.sh runs) -- so an unsupported region
    # is caught before any billable provisioning happens, not after all of it already has.
    validate_domain_mapping_region "$GCP_REGION"
fi

# ------------------------------------------------------------------------------
# DATABASE OPTION
# ------------------------------------------------------------------------------
# Every option under db/ validates its own inputs and exports the same interface, which is all any
# later step may use (see deploy/CONTRACT.md, section 1):
#   DB_ENV_VARS       comma-separated KEY=value pairs for --set-env-vars
#   DB_UPDATE_FLAGS   array of extra flags for `gcloud run deploy` / `run jobs update`
#   DB_CREATE_FLAGS   array of extra flags for `gcloud run jobs create`
#   DB_REQUIRED_APIS  array of extra service APIs project.sh must enable
#   db_provision      function that database.sh calls to create/verify the database
DB_OPTION_FILE="$GCP_TOOLKIT_DIR/db/$DB_PROVIDER.sh"
if [ ! -f "$DB_OPTION_FILE" ]; then
    log_error "Unknown DB_PROVIDER '$DB_PROVIDER' -- no $DB_OPTION_FILE. Available: $(ls "$GCP_TOOLKIT_DIR/db" | sed 's/\.sh$//' | tr '\n' ' ')"
    exit 1
fi
source "$DB_OPTION_FILE"

log_success "All configuration parameters validated successfully (shell-safe)."

# Written last, so secrets generated by the database option are saved too.
save_secrets "$GCP_SECRETS_FILE"
