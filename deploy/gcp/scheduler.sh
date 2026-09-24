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

# 3. Provision Cloud Scheduler Job
SCHEDULER_JOB_NAME="$DEPLOYMENT_NAME-queue-scheduler"
SCHEDULER_SCHEDULE="*/5 * * * *" # Every 5 minutes
SCHEDULER_URI="${SERVICE_URL}/api/v1/queue/process?token=${QUEUE_TRIGGER_TOKEN}"

log_info "Checking if Cloud Scheduler Job ($SCHEDULER_JOB_NAME) already exists..."
if gcloud scheduler jobs describe "$SCHEDULER_JOB_NAME" --location="$GCP_REGION" &>/dev/null; then
    log_info "Cloud Scheduler Job already exists. Updating configuration..."
    gcloud scheduler jobs update http "$SCHEDULER_JOB_NAME" \
        --location="$GCP_REGION" \
        --schedule="$SCHEDULER_SCHEDULE" \
        --uri="$SCHEDULER_URI" \
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
        --http-method="POST" \
        --time-zone="UTC" \
        --quiet
    log_success "Cloud Scheduler Job ($SCHEDULER2_JOB_NAME) provisioned successfully."
fi

log_info "================================================================"
log_success "GOOGLE CLOUD SCHEDULER TRIGGER CONFIGURATION COMPLETE!"
log_info "================================================================"
