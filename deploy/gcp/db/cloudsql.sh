#!/bin/bash
# ==============================================================================
# ZERO CMS - GCP DATABASE OPTION: CLOUD SQL (MYSQL 8.0)
# ==============================================================================
# Sourced by config.sh when DB_PROVIDER=cloudsql (the default). Exports the
# database interface described in config.sh; db_provision creates (or reuses)
# a low-cost shared-core db-f1-micro instance, the database and its user.
# Cloud Run reaches the instance over the /cloudsql unix socket.
# ==============================================================================

export CLOUDSQL_INSTANCE="${CLOUDSQL_INSTANCE:-zerocms-db}"         # Cloud SQL MySQL instance name
export DB_NAME="${DB_NAME:-zerocms_db}"                   # Name of the production database
export DB_USER="${DB_USER:-zerocms_db_user}"             # Production database user
export DB_PASS="${DB_PASS:-}"                 # Generate randomly or read from persistent file

# By default, we provision a fresh Cloud SQL instance. Set to false to instead reuse an existing
# instance (CLOUDSQL_INSTANCE points at one that already exists, possibly provisioned by hand).
export CREATE_CLOUDSQL="${CREATE_CLOUDSQL:-true}"

ensure_secret DB_PASS password

validate_safe_string "$CLOUDSQL_INSTANCE" "CLOUDSQL_INSTANCE"
validate_safe_string "$DB_NAME" "DB_NAME"
validate_safe_string "$DB_USER" "DB_USER"
validate_safe_string "$DB_PASS" "DB_PASS"

CLOUDSQL_CONNECTION_NAME="$GCP_PROJECT_ID:$GCP_REGION:$CLOUDSQL_INSTANCE"

export DB_ENV_VARS="DB_SOCKET=/cloudsql/$CLOUDSQL_CONNECTION_NAME,DB_USER=$DB_USER,DB_PASS=$DB_PASS,DB_NAME=$DB_NAME"
# --clear-secrets drops a CA-certificate mount left behind by a previous DB_PROVIDER=aiven deploy.
DB_UPDATE_FLAGS=(--set-cloudsql-instances="$CLOUDSQL_CONNECTION_NAME" --clear-secrets)
DB_CREATE_FLAGS=(--set-cloudsql-instances="$CLOUDSQL_CONNECTION_NAME")
DB_REQUIRED_APIS=(sqladmin.googleapis.com)

db_provision() {
    if [ "$CREATE_CLOUDSQL" != true ]; then
        log_info "CREATE_CLOUDSQL is false. Skipping Cloud SQL provisioning and reusing existing instance ($CLOUDSQL_INSTANCE)."
        log_warn "Ensure database '$DB_NAME' and user '$DB_USER' (with the configured DB_PASS) already exist on that instance."
        return 0
    fi

    log_info "Checking Cloud SQL MySQL instance ($CLOUDSQL_INSTANCE)..."
    if gcloud sql instances describe "$CLOUDSQL_INSTANCE" &>/dev/null; then
        log_success "Cloud SQL instance $CLOUDSQL_INSTANCE already exists."

        # If the instance exists but is stopped, start it dynamically
        local activation_policy current_status
        activation_policy=$(gcloud sql instances describe "$CLOUDSQL_INSTANCE" --format="value(settings.activationPolicy)")
        if [ "$activation_policy" != "ALWAYS" ]; then
            log_warn "Cloud SQL instance exists but is deactivated (policy: $activation_policy). Setting activation policy to ALWAYS (starting instance)..."
            # --async avoids gcloud's own synchronous wait, which has been observed to give up with a
            # "taking longer than expected" error on instance starts that take upwards of 10+ minutes,
            # aborting this script under `set -e` even though the operation goes on to succeed. Poll the
            # instance's actual state ourselves instead, with no arbitrary client-side deadline.
            gcloud sql instances patch "$CLOUDSQL_INSTANCE" --activation-policy=ALWAYS --async --quiet
            log_info "Waiting for Cloud SQL instance to spin up and become RUNNABLE..."
            while :; do
                # A healthy, started Cloud SQL instance reports state=RUNNABLE (not "RUNNING" -- that
                # value never appears in the API's actual state enum).
                current_status=$(gcloud sql instances describe "$CLOUDSQL_INSTANCE" --format="value(state)")
                if [ "$current_status" = "RUNNABLE" ]; then
                    break
                fi
                log_info "Still waiting (current status: $current_status)..."
                sleep 10
            done
            log_success "Cloud SQL instance is now RUNNABLE."
        fi
    else
        log_info "Creating low-cost shared-core db-f1-micro MySQL 8.0 instance $CLOUDSQL_INSTANCE in region $GCP_REGION..."
        log_warn "This provisioning process can take several minutes to complete."
        gcloud sql instances create "$CLOUDSQL_INSTANCE" \
          --database-version=MYSQL_8_0 \
          --tier=db-f1-micro \
          --region="$GCP_REGION" \
          --storage-size=10GB \
          --storage-type=HDD \
          --availability-type=zonal
        log_success "Cloud SQL instance created successfully."
    fi

    log_info "Checking database ($DB_NAME) on Cloud SQL instance..."
    if gcloud sql databases describe "$DB_NAME" --instance="$CLOUDSQL_INSTANCE" &>/dev/null; then
        log_success "Database $DB_NAME already exists."
    else
        log_info "Creating database $DB_NAME..."
        gcloud sql databases create "$DB_NAME" --instance="$CLOUDSQL_INSTANCE"
        log_success "Database created successfully."
    fi

    log_info "Checking DB user ($DB_USER) on Cloud SQL instance..."
    if gcloud sql users list --instance="$CLOUDSQL_INSTANCE" --format="value(name)" | grep -Fxq "$DB_USER"; then
        log_warn "DB User $DB_USER already exists. Recreating user with '%' host wildcard to synchronize credentials..."
        # Attempt deletion on both possible hosts to ensure an absolutely clean state
        gcloud sql users delete "$DB_USER" --host="%" --instance="$CLOUDSQL_INSTANCE" --quiet &>/dev/null || true
        gcloud sql users delete "$DB_USER" --host="" --instance="$CLOUDSQL_INSTANCE" --quiet &>/dev/null || true
        log_success "Stale DB User deleted."
    fi

    log_info "Creating DB user $DB_USER with secure password and '%' wildcard host..."
    gcloud sql users create "$DB_USER" \
      --instance="$CLOUDSQL_INSTANCE" \
      --password="$DB_PASS" \
      --host="%"
    log_success "DB User created and credential-synchronized successfully."
}
