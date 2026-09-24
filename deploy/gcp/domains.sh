#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP DOMAINS: CLOUD RUN CUSTOM DOMAIN MAPPING
# ==============================================================================
# Maps one or more custom domains (e.g. client-owned domains CNAMEd at this
# deployment) onto the Cloud Run service, so traffic for those domains reaches
# the same service as the default *.run.app URL. Optional/idempotent: skipped
# entirely unless DOMAIN_MAPPINGS is set.
#
# This only handles the infrastructure/DNS/TLS side. Zero CMS resolves tenants
# by an exact match on the `sites.domain` column against the incoming Host
# header (see src/Core/Concerns/ResolvesTenantContext.php) -- every domain
# mapped here must ALSO be registered against a tenant in the Sites admin
# area, or requests to it will hit the site-not-found page.
# ==============================================================================

source "$(dirname "$0")/config.sh"

if [ -z "$DOMAIN_MAPPINGS" ]; then
    log_info "DOMAIN_MAPPINGS is not set. Skipping custom domain mapping step."
    exit 0
fi

log_info "================================================================"
log_info "STARTING CLOUD RUN CUSTOM DOMAIN MAPPING"
log_info "================================================================"

if ! gcloud run services describe "$SERVICE_NAME" --region="$GCP_REGION" &>/dev/null; then
    log_error "Cloud Run service '$SERVICE_NAME' not found in region '$GCP_REGION'."
    log_error "Run './deploy/gcp/service.sh' (or the full setup.sh pipeline) first."
    exit 1
fi

log_warn "Each domain below must already be verified as owned by this GCP account/project"
log_warn "at https://search.google.com/search-console/ownership -- 'gcloud run domain-mappings"
log_warn "create' fails with a permission error otherwise. Verify apex domains (client-a.com),"
log_warn "not just the 'www' subdomain, since Search Console verification is per registrable domain."

IFS=',' read -ra DOMAINS <<< "$DOMAIN_MAPPINGS"
for RAW_DOMAIN in "${DOMAINS[@]}"; do
    DOMAIN="$(echo "$RAW_DOMAIN" | xargs)" # trim whitespace
    [ -z "$DOMAIN" ] && continue

    if [[ ! "$DOMAIN" =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)+$ ]]; then
        log_error "Skipping '$DOMAIN' -- does not look like a valid hostname."
        continue
    fi

    log_info "----------------------------------------------------------------"
    log_info "Mapping domain: $DOMAIN"
    log_info "----------------------------------------------------------------"

    if gcloud beta run domain-mappings describe --domain="$DOMAIN" --region="$GCP_REGION" &>/dev/null; then
        log_success "Domain mapping for $DOMAIN already exists. Leaving it in place (domain-mappings has no update verb; delete and re-run to change the target service)."
    else
        log_info "Creating domain mapping for $DOMAIN -> $SERVICE_NAME..."
        if ! gcloud beta run domain-mappings create \
              --service="$SERVICE_NAME" \
              --domain="$DOMAIN" \
              --region="$GCP_REGION" \
              --quiet; then
            log_error "Failed to create domain mapping for $DOMAIN."
            log_error "Common causes: domain ownership not verified in Search Console for this"
            log_error "account, or this GCP_REGION does not support domain mappings (retry with"
            log_error "GCP_REGION=us-central1 or see: gcloud beta run domain-mappings create --help)."
            continue
        fi
        log_success "Domain mapping created for $DOMAIN."
    fi

    log_info "Required DNS records for $DOMAIN (add these with the domain's DNS provider):"
    gcloud beta run domain-mappings describe --domain="$DOMAIN" --region="$GCP_REGION" \
      --format="table(status.resourceRecords[].name, status.resourceRecords[].type, status.resourceRecords[].rrdata)" \
      2>/dev/null || log_warn "Could not read back DNS records yet -- re-run this script in a minute, or check: gcloud beta run domain-mappings describe --domain=$DOMAIN --region=$GCP_REGION"

    log_warn "Managed SSL cert provisioning for $DOMAIN only starts once its DNS records resolve"
    log_warn "correctly, and can take from a few minutes up to ~24 hours. Track it with:"
    log_warn "  gcloud beta run domain-mappings describe --domain=$DOMAIN --region=$GCP_REGION"
    log_warn "Do not forget the app-level step: $DOMAIN must also be added as a tenant's domain"
    log_warn "in the Sites admin area, or requests to it will hit the site-not-found page."
done

log_info "================================================================"
log_success "CLOUD RUN CUSTOM DOMAIN MAPPING STEP COMPLETE"
log_info "================================================================"
