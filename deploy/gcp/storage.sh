#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP OBJECT STORAGE (GCS)
# ==============================================================================
# Provisions a public, uniform-access Google Cloud Storage bucket to serve as a
# fast CDN for dynamic media uploads (STORAGE_DRIVER=gcs).
# ==============================================================================

source "$(dirname "$0")/config.sh"

# ------------------------------------------------------------------------------
# PROVISION CLOUD STORAGE BUCKET (GCS)
# ------------------------------------------------------------------------------
if [ "$CREATE_STORAGE_BUCKET" != true ]; then
    log_info "CREATE_STORAGE_BUCKET is false. Skipping bucket provisioning and reusing existing bucket (gs://$GCS_BUCKET_NAME)."
    log_warn "Ensure the bucket already exists and its IAM/ACL policy matches how STORAGE_DRIVER=gcs expects to serve media."
    exit 0
fi

log_info "Checking Google Cloud Storage bucket (gs://$GCS_BUCKET_NAME)..."
if gcloud storage buckets describe "gs://$GCS_BUCKET_NAME" &>/dev/null; then
    log_success "GCS bucket gs://$GCS_BUCKET_NAME already exists."
else
    log_info "Creating optimized, uniform-access GCS bucket gs://$GCS_BUCKET_NAME in region $GCP_REGION..."
    gcloud storage buckets create "gs://$GCS_BUCKET_NAME" \
      --location="$GCP_REGION" \
      --uniform-bucket-level-access
    log_success "GCS bucket created successfully."
fi

# Enforce public read access (Storage Object Viewer) on the GCS bucket so browsers can load assets
log_info "Enforcing public read-only IAM binding on bucket gs://$GCS_BUCKET_NAME..."
gcloud storage buckets add-iam-policy-binding "gs://$GCS_BUCKET_NAME" \
  --member="allUsers" \
  --role="roles/storage.objectViewer" --quiet
log_success "GCS bucket configured as a public asset CDN successfully."
