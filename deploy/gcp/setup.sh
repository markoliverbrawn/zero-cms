#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP DEPLOYMENT PIPELINE
# ==============================================================================
# Runs every GCP step in order. Sourcing config.sh first resolves settings and
# generates any missing secrets once, so each step (which re-sources it) sees
# the same values. Every step is idempotent: re-running the pipeline against an
# existing deployment updates it in place. Run from the project root:
#   ./deploy/gcp/setup.sh
# ==============================================================================

SCRIPT_DIR="$(dirname "$0")"

source "${SCRIPT_DIR}/config.sh"

log_info "================================================================"
log_info "STARTING GCP DEPLOYMENT PIPELINE"
log_info "================================================================"

# Authenticate, select the project and enable the service APIs
"${SCRIPT_DIR}/project.sh"

# Provision the public Cloud Storage bucket for uploads
"${SCRIPT_DIR}/storage.sh"

# Provision or verify the database (option selected by DB_PROVIDER)
"${SCRIPT_DIR}/database.sh"

# Build and push the image, deploy the Cloud Run service, run the migrate/seed jobs
"${SCRIPT_DIR}/service.sh"

# Provision the Cloud Scheduler HTTP triggers
"${SCRIPT_DIR}/scheduler.sh"

# Map custom domains onto the Cloud Run service (no-op unless DOMAIN_MAPPINGS is set)
"${SCRIPT_DIR}/domains.sh"

log_info "================================================================"
log_success "GCP DEPLOYMENT PIPELINE COMPLETE!"
log_info "================================================================"
