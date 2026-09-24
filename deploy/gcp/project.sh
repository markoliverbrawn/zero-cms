#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP PROJECT: AUTHENTICATION & SERVICE APIS
# ==============================================================================
# Verifies the gcloud session, selects the target project and enables every
# service API the pipeline needs, including any the database option asks for.
# ==============================================================================

source "$(dirname "$0")/config.sh"

# ------------------------------------------------------------------------------
# 1. GCP AUTHENTICATION & PROJECT SELECTION
# ------------------------------------------------------------------------------
log_info "Starting GCP setup. Verifying authentication..."

# Check if logged in. If not, trigger login.
if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" | grep -q "@"; then
    log_warn "No active GCP account session found. Initiating authentication..."
    gcloud auth login
fi

log_info "Configuring active project to: $GCP_PROJECT_ID"
gcloud config set project "$GCP_PROJECT_ID"

# ------------------------------------------------------------------------------
# 2. ENABLE GCP SERVICES (APIs)
# ------------------------------------------------------------------------------
log_info "Enabling required Google Cloud Service APIs (this might take a few moments)..."
gcloud services enable \
  run.googleapis.com \
  storage.googleapis.com \
  cloudbuild.googleapis.com \
  artifactregistry.googleapis.com \
  cloudscheduler.googleapis.com \
  "${DB_REQUIRED_APIS[@]}"

log_success "Service APIs enabled successfully."
