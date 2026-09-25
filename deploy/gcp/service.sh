#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP SERVICE: IMAGE BUILD, CLOUD RUN DEPLOY & ONE-OFF JOBS
# ==============================================================================
# Builds the shared image (deploy/image/), pushes it, deploys the stateless web
# service to Cloud Run, then runs the migrate job and (only if RUN_SEED=true)
# the destructive seed job. Database wiring comes entirely from the selected
# database option's DB_ENV_VARS / DB_UPDATE_FLAGS / DB_CREATE_FLAGS.
#
# All runtime configuration reaches the containers as Cloud Run env vars; no
# .env file is written into the image (deploy/image/.dockerignore excludes the
# project's own local one).
# ==============================================================================

source "$(dirname "$0")/config.sh"

# ------------------------------------------------------------------------------
# 1. PRE-FLIGHT CHECKS
# ------------------------------------------------------------------------------
# A host project installs Core with Composer (vendor/ is gitignored), and the image is built from
# the project root as-is, so vendor/ must already be populated -- fail here rather than obscurely
# inside the image build or at first request.
if [ -f composer.json ] && grep -q '"markoliverbrawn/zero-cms-core"' composer.json && [ ! -d vendor/markoliverbrawn/zero-cms-core ]; then
    log_error "vendor/markoliverbrawn/zero-cms-core not found. Run 'composer install' in $PROJECT_ROOT first (in CI, as a step before this one)."
    exit 1
fi
# The one-off jobs run these from the project root inside the image.
REQUIRED_SCRIPTS=()
[ "$RUN_MIGRATIONS" = true ] && REQUIRED_SCRIPTS+=(bin/migrate)
[ "$RUN_SEED" = true ] && REQUIRED_SCRIPTS+=(bin/seed)
for script in "${REQUIRED_SCRIPTS[@]}"; do
    if [ ! -f "$script" ]; then
        log_error "$PROJECT_ROOT/$script not found -- the migrate/seed jobs run it from the project root. A host project needs its own wrapper (see bin/seed in Core)."
        exit 1
    fi
done

# Optional project hook (deploy/CONTRACT.md, section 6): extra KEY=value pairs, comma-separated,
# appended to the web service's and every job's environment.
export EXTRA_ENV_VARS="${EXTRA_ENV_VARS:-}"
if [ -n "$EXTRA_ENV_VARS" ] && [[ ! "$EXTRA_ENV_VARS" =~ ^[A-Z][A-Z0-9_]*=[^,]*(,[A-Z][A-Z0-9_]*=[^,]*)*$ ]]; then
    log_error "EXTRA_ENV_VARS must be comma-separated KEY=value pairs (values cannot contain commas)."
    exit 1
fi

# Runtime environment shared by the web service and every job (deploy/CONTRACT.md, section 3).
# Optional values are only included when set.
APP_ENV_VARS="$DB_ENV_VARS,STORAGE_DRIVER=gcs,GCS_BUCKET=$GCS_BUCKET_NAME,GCS_BUCKET_NAME=$GCS_BUCKET_NAME,GCS_PRIVATE_BUCKET_NAME=$GCS_PRIVATE_BUCKET_NAME,ENVIRONMENT=production,BENCHMARKING=false,QUEUE_TRIGGER_TOKEN=$QUEUE_TRIGGER_TOKEN,SCHEDULER_TRIGGER_TOKEN=$SCHEDULER_TRIGGER_TOKEN,TRUSTED_PROXY_SECRET=$TRUSTED_PROXY_SECRET,APP_KEY=$APP_KEY"
APP_ENV_VARS+="$(env_pairs ADMIN_EMAIL SMTP_HOST SMTP_PORT SMTP_SECURE SMTP_USER SMTP_PASS SMTP_FROM_EMAIL SMTP_FROM_NAME)"
APP_ENV_VARS+="${EXTRA_ENV_VARS:+,$EXTRA_ENV_VARS}"

# ------------------------------------------------------------------------------
# BUILD-CONTEXT FILES (copied into the project root for the build, removed on exit)
# ------------------------------------------------------------------------------
# The image files are provider-neutral; .gcloudignore only matters to remote Cloud Build. A project
# can add its own rules in .deploy/dockerignore (what the image leaves out) and .deploy/gcloudignore
# (what a remote build doesn't upload); each is appended to the shared file of the same name.
BUILD_FILES=(
    "$DEPLOY_TOOLKIT_DIR/image/Dockerfile:Dockerfile"
    "$DEPLOY_TOOLKIT_DIR/image/.dockerignore:.dockerignore"
    "$DEPLOY_TOOLKIT_DIR/image/entrypoint.sh:entrypoint.sh"
    "$GCP_TOOLKIT_DIR/.gcloudignore:.gcloudignore"
)
PROJECT_IGNORE_FILES=(
    "$PROJECT_ROOT/.deploy/dockerignore:.dockerignore"
    "$PROJECT_ROOT/.deploy/gcloudignore:.gcloudignore"
)
COPIED_BUILD_FILES=()

cleanup_build_files() {
    local file
    for file in "${COPIED_BUILD_FILES[@]}"; do
        log_info "Cleaning up temporary $file from root..."
        rm -f "$file"
    done
}
trap cleanup_build_files EXIT INT TERM

# ------------------------------------------------------------------------------
# 2. BUILD & UPLOAD CONTAINER IMAGE
# ------------------------------------------------------------------------------
IMAGE_NAME="gcr.io/$GCP_PROJECT_ID/$DEPLOYMENT_NAME-app:$IMAGE_TAG"

# Copy deployment configurations to root temporarily for the duration of build execution
log_info "Copying isolated deployment files temporarily to root for build compilation..."
for entry in "${BUILD_FILES[@]}"; do
    src="${entry%%:*}"
    dest="${entry##*:}"
    if [ -e "$dest" ]; then
        # The copy is deleted again on exit, so never overwrite a file the project owns.
        log_error "$PROJECT_ROOT/$dest already exists. Move it aside -- the build copies its own $dest there and removes it afterwards."
        exit 1
    fi
    cp "$src" "$dest"
    COPIED_BUILD_FILES+=("$dest")
done
for entry in "${PROJECT_IGNORE_FILES[@]}"; do
    src="${entry%%:*}"
    dest="${entry##*:}"
    if [ -f "$src" ]; then
        log_info "Appending project-specific ignore rules from $src to $dest..."
        { echo; echo "# --- from $src"; cat "$src"; } >> "$dest"
    fi
done

if [ "$USE_LOCAL_DOCKER" = true ]; then
    log_info "----------------------------------------------------------------"
    log_info "BUILD PATH: Local Docker Engine compilation"
    log_info "----------------------------------------------------------------"
    log_info "Building container image LOCALLY..."
    docker build -t "$IMAGE_NAME" .

    log_info "Configuring Docker credential helper..."
    gcloud auth configure-docker gcr.io --quiet

    log_info "Pushing compiled image to Google Container Registry..."
    docker push "$IMAGE_NAME"
    log_success "Docker image successfully pushed."
else
    log_info "----------------------------------------------------------------"
    log_info "BUILD PATH: Remote Google Cloud Build compilation"
    log_info "----------------------------------------------------------------"

    # Solve Region/Location Constraint Policy mismatch for regional constraints (like AU)
    # Create a dedicated Cloud Build staging bucket in your local GCP_REGION
    BUILD_STAGING_BUCKET="${GCP_PROJECT_ID}-cloudbuild-staging"
    log_info "Checking regional Cloud Build staging bucket (gs://$BUILD_STAGING_BUCKET)..."
    if gcloud storage buckets describe "gs://$BUILD_STAGING_BUCKET" &>/dev/null; then
        log_success "Staging bucket gs://$BUILD_STAGING_BUCKET already exists."
    else
        log_info "Creating regional staging bucket gs://$BUILD_STAGING_BUCKET in $GCP_REGION..."
        gcloud storage buckets create "gs://$BUILD_STAGING_BUCKET" \
          --location="$GCP_REGION" \
          --uniform-bucket-level-access
        log_success "Regional staging bucket created successfully."
    fi

    # Every remote build uploads the full project source here and nothing ever removes it, so the
    # bucket grows by one archive per deploy. Cloud Build only needs an upload while its build
    # runs; the retention window just keeps recent ones around for debugging a build. Applied to a
    # new bucket, or an existing one with no lifecycle policy -- never over rules someone set.
    if [ -z "$(gcloud storage buckets describe "gs://$BUILD_STAGING_BUCKET" --format="value(lifecycle_config)")" ]; then
        log_info "Adding a lifecycle rule to delete build sources after $BUILD_SOURCE_RETENTION_DAYS days..."
        LIFECYCLE_FILE="$(mktemp)"
        printf '{"rule":[{"action":{"type":"Delete"},"condition":{"age":%s,"matchesPrefix":["source/"]}}]}\n' \
          "$BUILD_SOURCE_RETENTION_DAYS" > "$LIFECYCLE_FILE"
        gcloud storage buckets update "gs://$BUILD_STAGING_BUCKET" --lifecycle-file="$LIFECYCLE_FILE"
        rm -f "$LIFECYCLE_FILE"
        log_success "Build sources in gs://$BUILD_STAGING_BUCKET/source/ now expire after $BUILD_SOURCE_RETENTION_DAYS days."
    else
        log_info "Staging bucket already has a lifecycle policy; leaving it unchanged."
    fi

    log_info "Submitting build request to Google Cloud Build. Target image: $IMAGE_NAME"
    # Pass the regional staging bucket to avoid violating regional constraint policies.
    # Add --verbosity=debug to trace REST calls when debugging a hang. Not on by default: it logs
    # every poll of the streamed build log (about one line a second), burying the build output.
    gcloud builds submit --tag "$IMAGE_NAME" --gcs-source-staging-dir="gs://$BUILD_STAGING_BUCKET/source"
    log_success "Docker image successfully compiled and uploaded."
fi

# ------------------------------------------------------------------------------
# 3. DEPLOY STATELESS WEB SERVICE TO CLOUD RUN
# ------------------------------------------------------------------------------
log_info "----------------------------------------------------------------"
log_info "Deploying stateless Web Service ($SERVICE_NAME) to Cloud Run"
log_info "----------------------------------------------------------------"
# --timeout=900 raises the request timeout from Cloud Run's 5-minute default -- the same cadence
# as the Cloud Scheduler jobs hitting this service (scheduler.sh). /api/v1/queue/process drains
# pending jobs for up to 800s per call (QueueManager::runPendingJobs()); at the 5-minute default, a
# legitimately slow job gets killed mid-request, and its row then sits 'reserved' until
# QueueManager's own 900s stale-lock window elapses before it's retried. Matching the Cloud Run
# timeout to that same 900s means a killed request's job becomes reclaimable immediately rather
# than needing to wait out an extra window on top.

gcloud run deploy "$SERVICE_NAME" \
  --image "$IMAGE_NAME" \
  --min-instances=0 \
  --max-instances=10 \
  --memory=512Mi \
  --cpu=1 \
  --concurrency=80 \
  --timeout=900 \
  "${DB_UPDATE_FLAGS[@]}" \
  --set-env-vars="$APP_ENV_VARS" \
  --region="$GCP_REGION" \
  --allow-unauthenticated

# Retrieve the assigned Service URL
SERVICE_URL=$(gcloud run services describe "$SERVICE_NAME" --region="$GCP_REGION" --format="value(status.url)")
log_success "Stateless web service successfully deployed. URL: $SERVICE_URL"

# Patch the deployed service with its own BASE_URL dynamically
log_info "Patching stateless Web Service environment with active BASE_URL..."
gcloud run services update "$SERVICE_NAME" \
  --region="$GCP_REGION" \
  --update-env-vars="BASE_URL=$SERVICE_URL"

log_success "Web service environment configuration updated with BASE_URL."

# ------------------------------------------------------------------------------
# ONE-OFF JOBS (same image and environment as the web service, different command)
# ------------------------------------------------------------------------------
# The jobs also get the admin credentials (applied by bin/seed) and BASE_URL (the seeders set the
# default site's domain from it).
JOB_ENV_VARS="$APP_ENV_VARS,ADMIN_USER=$ADMIN_USER,ADMIN_PASS=$ADMIN_PASS,BASE_URL=$SERVICE_URL"

# Creates or updates Cloud Run job NAME to run `php SCRIPT`, then executes it and waits.
# Usage: run_job NAME SCRIPT
run_job() {
    local job_name="$1" script="$2"
    log_info "Preparing isolated Google Cloud Run Job ($job_name)..."

    # Check if the job already exists to decide whether to create or update it
    if gcloud run jobs describe "$job_name" --region="$GCP_REGION" &>/dev/null; then
        log_info "Job $job_name already exists. Updating configuration..."
        gcloud run jobs update "$job_name" \
          --image "$IMAGE_NAME" \
          --command="php" \
          --args="$script" \
          "${DB_UPDATE_FLAGS[@]}" \
          --set-env-vars="$JOB_ENV_VARS" \
          --region="$GCP_REGION"
    else
        log_info "Creating new Cloud Run Job $job_name..."
        gcloud run jobs create "$job_name" \
          --image "$IMAGE_NAME" \
          --command="php" \
          --args="$script" \
          "${DB_CREATE_FLAGS[@]}" \
          --set-env-vars="$JOB_ENV_VARS" \
          --region="$GCP_REGION"
    fi

    log_info "Executing Cloud Run Job $job_name ($script)..."
    gcloud run jobs execute "$job_name" --region="$GCP_REGION" --wait
}

# ------------------------------------------------------------------------------
# 4. RUN SAFE DB MIGRATIONS (SCHEMA UP-ONLY, NO DATA LOSS) -- CONDITIONAL
# ------------------------------------------------------------------------------
if [ "$RUN_MIGRATIONS" = true ]; then
    log_info "----------------------------------------------------------------"
    log_info "STEP 4: Executing Safe Database Schema Migrations"
    log_info "----------------------------------------------------------------"
    run_job "$DEPLOYMENT_NAME-migrate-job" "bin/migrate"
    log_success "Database schema successfully migrated."
else
    log_info "----------------------------------------------------------------"
    log_info "STEP 4: Database Migrations Bypassed"
    log_info "----------------------------------------------------------------"
    log_info "RUN_MIGRATIONS is set to false. Skipping schema migration step for this deploy."
fi

# ------------------------------------------------------------------------------
# 5. RUN DESTRUCTIVE SEEDERS (WIPES ALL DATA AND SEEDS FRESH KITCHENSINK MODEL)
# ------------------------------------------------------------------------------
if [ "$RUN_SEED" = true ]; then
    log_info "----------------------------------------------------------------"
    log_warn "STEP 5: Executing Destructive Multi-Tenant Database Seeders!"
    log_info "----------------------------------------------------------------"
    log_warn "RUN_SEED is set to TRUE. All existing tables and data will be wiped!"
    run_job "$DEPLOYMENT_NAME-seed-job" "bin/seed"
    log_success "Database migrations and multi-tenant seeder executed successfully."
else
    log_info "----------------------------------------------------------------"
    log_info "STEP 5: Database Seeding Bypassed"
    log_info "----------------------------------------------------------------"
    log_info "RUN_SEED is set to false (or unspecified). Destructive seeder bypassed to preserve production data."
fi

# ==============================================================================
# PIPELINE COMPLETE
# ==============================================================================
log_info "----------------------------------------------------------------"
log_success "ZERO CMS HAS BEEN SUCCESSFULLY DEPLOYED TO GOOGLE CLOUD RUN!"
log_success "Public URL: ${GREEN}$SERVICE_URL${NC}"
log_info "----------------------------------------------------------------"
