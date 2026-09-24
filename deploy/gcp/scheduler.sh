#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP SCHEDULER: CLOUD SCHEDULER HTTP TRIGGERS
# ==============================================================================
# Sets up the two Cloud Scheduler jobs that wake the Cloud Run service to drain
# the queue (/api/v1/queue/process) and enqueue scheduled tasks
# (/api/v1/queue/schedule). There is no long-running worker on Cloud Run.
# ==============================================================================

source "$(dirname "$0")/config.sh"

log_info "================================================================"
log_info "STARTING GOOGLE CLOUD SCHEDULER SERVICE CONFIGURATION"
log_info "================================================================"

# 1. Resolve Active Service URL
log_info "Resolving assigned HTTP endpoint URL for Cloud Run Service ($SERVICE_NAME)..."
SERVICE_URL=$(gcloud run services describe "$SERVICE_NAME" --region="$GCP_REGION" --format="value(status.url)" 2>/dev/null || true)

if [ -z "$SERVICE_URL" ]; then
    log_error "Could not resolve Cloud Run Service '$SERVICE_NAME' URL in region '$GCP_REGION'."
    log_error "Please run './deploy/gcp/setup.sh' first to deploy the application service prior to scheduler provisioning."
    exit 1
fi

log_success "Resolved Cloud Run Service endpoint: $SERVICE_URL"

# 2. QUEUE_TRIGGER_TOKEN and SCHEDULER_TRIGGER_TOKEN are resolved (and persisted) by
# config.sh above -- service.sh already pushed both into the Cloud Run service's env vars as
# part of the initial deploy, so this script only needs to build URLs from the same values.

# 2b. Tenant resolution for trigger calls (deploy/CONTRACT.md, section 4.1). The app resolves the
# tenant by exact match of the request host against `sites.domain` before any route runs. Calls to
# the *.run.app URL match only while the seeded default site's domain is still that host (the seed
# job sets it from BASE_URL). Once it moves to a real domain, set SCHEDULER_TARGET_DOMAIN to any real
# `sites.domain`: the triggers then send it as X-Forwarded-Host with the matching X-Proxy-Secret,
# which Security::resolveTrustedHost() trusts. The queue is global -- QueueManager processes every
# tenant's pending jobs whichever site resolved -- so one domain unblocks all tenants.
SCHEDULER_HEADER_ARGS_CREATE=()
SCHEDULER_HEADER_ARGS_UPDATE=()
if [ -n "$SCHEDULER_TARGET_DOMAIN" ]; then
    SCHEDULER_HEADERS="X-Forwarded-Host=${SCHEDULER_TARGET_DOMAIN},X-Proxy-Secret=${TRUSTED_PROXY_SECRET}"
    SCHEDULER_HEADER_ARGS_CREATE=(--headers="$SCHEDULER_HEADERS")
    SCHEDULER_HEADER_ARGS_UPDATE=(--update-headers="$SCHEDULER_HEADERS")
    log_info "Triggers will present X-Forwarded-Host: $SCHEDULER_TARGET_DOMAIN"
fi

# 3. Provision Cloud Scheduler Job
SCHEDULER_JOB_NAME="$DEPLOYMENT_NAME-queue-scheduler"
SCHEDULER_SCHEDULE="*/5 * * * *" # Every 5 minutes
SCHEDULER_URI="${SERVICE_URL}/api/v1/queue/process?token=${QUEUE_TRIGGER_TOKEN}"
# The queue endpoint drains pending jobs for up to 800s per call (QueueManager::runPendingJobs()).
# Cloud Scheduler's default --attempt-deadline is 180s, and it cancels the request server-side when
# the deadline passes -- cutting off the job in progress. Set it above the 800s budget (headroom for
# the last job and the JSON response) but below the service's --timeout=900 (service.sh), so Cloud
# Run doesn't kill the request first.
SCHEDULER_ATTEMPT_DEADLINE="820s"

log_info "Checking if Cloud Scheduler Job ($SCHEDULER_JOB_NAME) already exists..."
if gcloud scheduler jobs describe "$SCHEDULER_JOB_NAME" --location="$GCP_REGION" &>/dev/null; then
    log_info "Cloud Scheduler Job already exists. Updating configuration..."
    gcloud scheduler jobs update http "$SCHEDULER_JOB_NAME" \
        --location="$GCP_REGION" \
        --schedule="$SCHEDULER_SCHEDULE" \
        --uri="$SCHEDULER_URI" \
        "${SCHEDULER_HEADER_ARGS_UPDATE[@]}" \
        --attempt-deadline="$SCHEDULER_ATTEMPT_DEADLINE" \
        --http-method="POST" \
        --time-zone="UTC" \
        --quiet
    log_success "Cloud Scheduler Job ($SCHEDULER_JOB_NAME) updated successfully."
else
    log_info "Creating new Cloud Scheduler Job ($SCHEDULER_JOB_NAME) under location ($GCP_REGION)..."
    gcloud scheduler jobs create http "$SCHEDULER_JOB_NAME" \
        --location="$GCP_REGION" \
        --schedule="$SCHEDULER_SCHEDULE" \
        --uri="$SCHEDULER_URI" \
        "${SCHEDULER_HEADER_ARGS_CREATE[@]}" \
        --attempt-deadline="$SCHEDULER_ATTEMPT_DEADLINE" \
        --http-method="POST" \
        --time-zone="UTC" \
        --quiet
    log_success "Cloud Scheduler Job ($SCHEDULER_JOB_NAME) provisioned successfully."
fi

# 4. Provision Recurring-Task Scheduler Job
# Reuses the same 5-minute cadence as the queue-processing job above: no registered task currently
# needs finer-grained ("every_minute") resolution, so there is nothing to gain yet from a tighter
# schedule, only extra Cloud Run invocation volume/cold-start churn. Tighten this schedule if an
# every_minute task is ever actually registered.
SCHEDULER2_JOB_NAME="$DEPLOYMENT_NAME-task-scheduler"
SCHEDULER2_SCHEDULE="*/5 * * * *" # Every 5 minutes
SCHEDULER2_URI="${SERVICE_URL}/api/v1/queue/schedule?token=${SCHEDULER_TRIGGER_TOKEN}"

log_info "Checking if Cloud Scheduler Job ($SCHEDULER2_JOB_NAME) already exists..."
if gcloud scheduler jobs describe "$SCHEDULER2_JOB_NAME" --location="$GCP_REGION" &>/dev/null; then
    log_info "Cloud Scheduler Job already exists. Updating configuration..."
    gcloud scheduler jobs update http "$SCHEDULER2_JOB_NAME" \
        --location="$GCP_REGION" \
        --schedule="$SCHEDULER2_SCHEDULE" \
        --uri="$SCHEDULER2_URI" \
        "${SCHEDULER_HEADER_ARGS_UPDATE[@]}" \
        --http-method="POST" \
        --time-zone="UTC" \
        --quiet
    log_success "Cloud Scheduler Job ($SCHEDULER2_JOB_NAME) updated successfully."
else
    log_info "Creating new Cloud Scheduler Job ($SCHEDULER2_JOB_NAME) under location ($GCP_REGION)..."
    gcloud scheduler jobs create http "$SCHEDULER2_JOB_NAME" \
        --location="$GCP_REGION" \
        --schedule="$SCHEDULER2_SCHEDULE" \
        --uri="$SCHEDULER2_URI" \
        "${SCHEDULER_HEADER_ARGS_CREATE[@]}" \
        --http-method="POST" \
        --time-zone="UTC" \
        --quiet
    log_success "Cloud Scheduler Job ($SCHEDULER2_JOB_NAME) provisioned successfully."
fi

log_info "================================================================"
log_success "GOOGLE CLOUD SCHEDULER TRIGGER CONFIGURATION COMPLETE!"
log_info "================================================================"
