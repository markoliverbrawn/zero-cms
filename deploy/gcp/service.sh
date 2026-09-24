#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP SERVICE: IMAGE BUILD, CLOUD RUN DEPLOY & ONE-OFF JOBS
# ==============================================================================
# Builds the shared image (deploy/image/), pushes it, deploys the stateless web
# service to Cloud Run, then runs the migrate job and (only if RUN_SEED=true)
# the destructive seed job. Database wiring comes entirely from the selected
# database option's DB_ENV_VARS / DB_UPDATE_FLAGS / DB_CREATE_FLAGS.
# ==============================================================================

source "$(dirname "$0")/config.sh"

# Build-context files copied into the project root for the duration of the build, then removed.
# The image files are provider-neutral; .gcloudignore only matters to remote Cloud Build.
BUILD_FILES=(
    "$DEPLOY_TOOLKIT_DIR/image/Dockerfile:Dockerfile"
    "$DEPLOY_TOOLKIT_DIR/image/.dockerignore:.dockerignore"
    "$DEPLOY_TOOLKIT_DIR/image/entrypoint.sh:entrypoint.sh"
    "$GCP_TOOLKIT_DIR/.gcloudignore:.gcloudignore"
)
COPIED_BUILD_FILES=()
BACKED_UP=false

# Optional project hook (deploy/CONTRACT.md, section 6): extra KEY=value pairs, comma-separated,
# appended to the web service's and every job's environment.
export EXTRA_ENV_VARS="${EXTRA_ENV_VARS:-}"
if [ -n "$EXTRA_ENV_VARS" ] && [[ ! "$EXTRA_ENV_VARS" =~ ^[A-Z][A-Z0-9_]*=[^,]*(,[A-Z][A-Z0-9_]*=[^,]*)*$ ]]; then
    log_error "EXTRA_ENV_VARS must be comma-separated KEY=value pairs (values cannot contain commas)."
    exit 1
fi
EXTRA_ENV_SUFFIX="${EXTRA_ENV_VARS:+,$EXTRA_ENV_VARS}"

# ------------------------------------------------------------------------------
# LOCAL ENVIRONMENT PRESERVATION (TRAP HOOKS)
# ------------------------------------------------------------------------------
restore_env() {
    local file
    if [ "$BACKED_UP" = true ]; then
        log_info "Restoring original local .env file..."
        if [ -f .env.bak ]; then
            mv .env.bak .env
            log_success "Original .env file successfully restored."
        else
            log_warn "Backup file .env.bak not found, could not restore."
        fi
    fi
    # Clean up temporary deployment files copied to root
    for file in "${COPIED_BUILD_FILES[@]}"; do
        log_info "Cleaning up temporary $file from root..."
        rm -f "$file"
    done
    # Clean up temporary production .env file if it was created
    if [ -f ".env" ] && [ "$BACKED_UP" = false ]; then
        log_info "Cleaning up temporary production .env file from root..."
        rm -f .env
    fi
}

# Trap exit/interruption signals to ensure local .env is ALWAYS restored and root is kept clean
trap restore_env EXIT INT TERM

# Backup the local .env if it exists
if [ -f ".env" ]; then
    log_info "Backing up existing local .env file..."
    cp .env .env.bak
    BACKED_UP=true
fi

# ------------------------------------------------------------------------------
# 1. CREATE TEMPORARY DEPLOYMENT .ENV
# ------------------------------------------------------------------------------
log_info "Creating temporary production .env file for the deployment context..."
cat <<EOF > .env
ENVIRONMENT=production
$(tr ',' '\n' <<< "$DB_ENV_VARS")
STORAGE_DRIVER=gcs
GCS_BUCKET=$GCS_BUCKET_NAME
GCS_BUCKET_NAME=$GCS_BUCKET_NAME
ADMIN_USER=$ADMIN_USER
ADMIN_PASS=$ADMIN_PASS
BENCHMARKING=false
QUEUE_TRIGGER_TOKEN=$QUEUE_TRIGGER_TOKEN
SCHEDULER_TRIGGER_TOKEN=$SCHEDULER_TRIGGER_TOKEN
EOF
log_success "Temporary .env file created."

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

    log_info "Submitting build request to Google Cloud Build. Target image: $IMAGE_NAME"
    # Pass the regional staging bucket to avoid violating regional constraint policies.
    # Use --verbosity=debug to output absolute REST traces and network progress to debug hangs.
    gcloud builds submit --tag "$IMAGE_NAME" --gcs-source-staging-dir="gs://$BUILD_STAGING_BUCKET/source" --verbosity=debug
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
  --set-env-vars="$DB_ENV_VARS,STORAGE_DRIVER=gcs,GCS_BUCKET=$GCS_BUCKET_NAME,GCS_BUCKET_NAME=$GCS_BUCKET_NAME,ENVIRONMENT=production,BENCHMARKING=false,QUEUE_TRIGGER_TOKEN=$QUEUE_TRIGGER_TOKEN,SCHEDULER_TRIGGER_TOKEN=$SCHEDULER_TRIGGER_TOKEN$EXTRA_ENV_SUFFIX" \
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
JOB_ENV_VARS="$DB_ENV_VARS,ADMIN_USER=$ADMIN_USER,ADMIN_PASS=$ADMIN_PASS,STORAGE_DRIVER=gcs,GCS_BUCKET=$GCS_BUCKET_NAME,GCS_BUCKET_NAME=$GCS_BUCKET_NAME,ENVIRONMENT=production,BENCHMARKING=false,BASE_URL=$SERVICE_URL,QUEUE_TRIGGER_TOKEN=$QUEUE_TRIGGER_TOKEN,SCHEDULER_TRIGGER_TOKEN=$SCHEDULER_TRIGGER_TOKEN$EXTRA_ENV_SUFFIX"

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
