#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP OBJECT STORAGE (GCS)
# ==============================================================================
# Provisions a public, uniform-access Google Cloud Storage bucket to serve as a
# fast CDN for dynamic media uploads (STORAGE_DRIVER=gcs), and a separate
# private bucket, never publicly readable, for files under storage/private/.
# ==============================================================================

source "$(dirname "$0")/config.sh"

# ------------------------------------------------------------------------------
# 1. PUBLIC MEDIA BUCKET (GCS)
# ------------------------------------------------------------------------------
if [ "$CREATE_STORAGE_BUCKET" != true ]; then
    log_info "CREATE_STORAGE_BUCKET is false. Skipping bucket provisioning and reusing existing bucket (gs://$GCS_BUCKET_NAME)."
    log_warn "Ensure the bucket already exists and its IAM/ACL policy matches how STORAGE_DRIVER=gcs expects to serve media."
else
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

    # Enforce public read access (Storage Object Viewer) on the GCS bucket so browsers can load
    # assets. This grant covers every object in the bucket -- IAM Conditions can't narrow a grant to
    # allUsers -- which is why private files live in the separate bucket below, never in this one.
    log_info "Enforcing public read-only IAM binding on bucket gs://$GCS_BUCKET_NAME..."
    gcloud storage buckets add-iam-policy-binding "gs://$GCS_BUCKET_NAME" \
      --member="allUsers" \
      --role="roles/storage.objectViewer" --quiet
    log_success "GCS bucket configured as a public asset CDN successfully."
fi

# ------------------------------------------------------------------------------
# 2. PRIVATE FILES BUCKET (GCS_PRIVATE_BUCKET_NAME)
# ------------------------------------------------------------------------------
# Holds everything the app stores under storage/private/ (e.g. backups, restore uploads). Served
# only through short-lived signed URLs. Public access prevention is enforced, so the bucket can
# never be granted to allUsers, even by mistake. Always ensured, independently of
# CREATE_STORAGE_BUCKET: creating a missing private bucket never touches existing data.
log_info "Checking private files bucket (gs://$GCS_PRIVATE_BUCKET_NAME)..."
if gcloud storage buckets describe "gs://$GCS_PRIVATE_BUCKET_NAME" &>/dev/null; then
    log_success "Private bucket gs://$GCS_PRIVATE_BUCKET_NAME already exists."
    gcloud storage buckets update "gs://$GCS_PRIVATE_BUCKET_NAME" --public-access-prevention
else
    log_info "Creating private, uniform-access bucket gs://$GCS_PRIVATE_BUCKET_NAME in region $GCP_REGION..."
    gcloud storage buckets create "gs://$GCS_PRIVATE_BUCKET_NAME" \
      --location="$GCP_REGION" \
      --uniform-bucket-level-access \
      --public-access-prevention
fi
log_success "Private bucket gs://$GCS_PRIVATE_BUCKET_NAME has public access prevention enforced."
