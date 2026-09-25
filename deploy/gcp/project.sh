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
  iamcredentials.googleapis.com \
  cloudresourcemanager.googleapis.com \
  "${DB_REQUIRED_APIS[@]}"

log_success "Service APIs enabled successfully."

# ------------------------------------------------------------------------------
# 3. LET THE RUNTIME SERVICE ACCOUNT SIGN URLS
# ------------------------------------------------------------------------------
# Private files are served through signed URLs. Without a key file, the app signs them as its own
# runtime service account via the IAM Credentials signBlob API, which needs
# roles/iam.serviceAccountTokenCreator on that account itself. Cloud Run runs as the project's
# Compute Engine default service account (<project-number>-compute@...) unless --service-account is
# passed (service.sh passes none); set RUNTIME_SERVICE_ACCOUNT to skip looking the number up, which
# needs the Cloud Resource Manager API (enabled above, but a newly enabled API can take minutes to
# take effect). Granting needs permission to change that account's IAM policy, which a CI deployer
# often lacks. Every failure here warns with the command to run once by hand rather than aborting:
# nothing else in the pipeline depends on it.
if [ -z "$RUNTIME_SERVICE_ACCOUNT" ]; then
    PROJECT_NUMBER=$(gcloud projects describe "$GCP_PROJECT_ID" --format="value(projectNumber)" 2>/dev/null || true)
    [ -n "$PROJECT_NUMBER" ] && RUNTIME_SERVICE_ACCOUNT="${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"
fi

if [ -z "$RUNTIME_SERVICE_ACCOUNT" ]; then
    log_warn "Could not look up the project number to find the runtime service account (the Cloud Resource Manager API may still be enabling)."
    log_warn "Set RUNTIME_SERVICE_ACCOUNT (e.g. <project-number>-compute@developer.gserviceaccount.com) to skip the lookup. Private file downloads"
    log_warn "need that account to hold roles/iam.serviceAccountTokenCreator on itself -- grant it once by hand if this step can't:"
    log_warn "  gcloud iam service-accounts add-iam-policy-binding <account> --member=serviceAccount:<account> --role=roles/iam.serviceAccountTokenCreator"
else
    log_info "Granting $RUNTIME_SERVICE_ACCOUNT permission to sign URLs as itself..."
    if gcloud iam service-accounts add-iam-policy-binding "$RUNTIME_SERVICE_ACCOUNT" \
         --member="serviceAccount:$RUNTIME_SERVICE_ACCOUNT" \
         --role="roles/iam.serviceAccountTokenCreator" \
         --condition=None --quiet >/dev/null; then
        log_success "Runtime service account can sign URLs."
    else
        log_warn "Could not grant roles/iam.serviceAccountTokenCreator (this deployer likely can't change service-account IAM)."
        log_warn "Private file downloads will fail until someone with that right runs, once:"
        log_warn "  gcloud iam service-accounts add-iam-policy-binding $RUNTIME_SERVICE_ACCOUNT \\"
        log_warn "    --member=serviceAccount:$RUNTIME_SERVICE_ACCOUNT --role=roles/iam.serviceAccountTokenCreator"
    fi
fi
