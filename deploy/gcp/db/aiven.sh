#!/bin/bash
# ==============================================================================
# ZERO CMS - GCP DATABASE OPTION: AIVEN FOR MYSQL
# ==============================================================================
# Sourced by config.sh when DB_PROVIDER=aiven. Connects every Cloud Run service
# and job to an external Aiven MySQL service over verified TLS (the app's
# DB_SSL_CA support). Aiven provisions the database itself, so db_provision
# only checks the CA secret exists.
#
# Needs, set up once:
#   AIVEN_CONNECTION_STRING  the Aiven console's Service URI verbatim
#                            (mysql://avnadmin:<pass>@<host>:<port>/defaultdb?ssl-mode=REQUIRED).
#                            Treat as a secret: set it in CI or the environment, not the
#                            committed settings file.
#   AIVEN_CA_SECRET          Secret Manager secret holding the Aiven project CA (ca.pem),
#                            readable by the Cloud Run runtime service account. Default aiven-ca.
#                              gcloud secrets create aiven-ca --replication-policy=automatic --data-file=ca.pem
#                              gcloud secrets add-iam-policy-binding aiven-ca \
#                                --member="serviceAccount:PROJECT_NUMBER-compute@developer.gserviceaccount.com" \
#                                --role="roles/secretmanager.secretAccessor"
#
# Switching DB_PROVIDER in either direction is just a redeploy: the update flags
# below clear the Cloud SQL attachment, and db/cloudsql.sh's clear the CA mount.
# Nothing copies data between the two databases.
# ==============================================================================

export AIVEN_CONNECTION_STRING="${AIVEN_CONNECTION_STRING:-}"
export AIVEN_CA_SECRET="${AIVEN_CA_SECRET:-aiven-ca}"
AIVEN_CA_MOUNT_PATH="/etc/aiven/ca.pem"

if [ -z "$AIVEN_CONNECTION_STRING" ]; then
    log_error "DB_PROVIDER=aiven but AIVEN_CONNECTION_STRING is not set. Set it in the environment (a secured CI variable for pipeline runs)."
    exit 1
fi
if [[ ! "$AIVEN_CONNECTION_STRING" =~ ^mysql://([^:@/]+):([^@/]+)@([^:/?]+):([0-9]+)/([^?]+) ]]; then
    log_error "AIVEN_CONNECTION_STRING is not in the expected mysql://user:pass@host:port/dbname form."
    exit 1
fi
# ssl-mode in the query string is ignored -- TLS is always enforced here via DB_SSL_CA, which also
# verifies the server certificate (ssl-mode=REQUIRED alone wouldn't).
AIVEN_DB_USER="${BASH_REMATCH[1]}"
AIVEN_DB_PASS="${BASH_REMATCH[2]}"
AIVEN_DB_HOST="${BASH_REMATCH[3]}"
AIVEN_DB_PORT="${BASH_REMATCH[4]}"
AIVEN_DB_NAME="${BASH_REMATCH[5]}"

for _aiven_var in AIVEN_DB_USER AIVEN_DB_HOST AIVEN_DB_PORT AIVEN_DB_NAME AIVEN_CA_SECRET; do
    validate_safe_string "${!_aiven_var}" "$_aiven_var"
done
unset _aiven_var
# Checked separately rather than via validate_safe_string, which echoes the offending value: CI
# systems mask a secured AIVEN_CONNECTION_STRING only as a whole, so the password substring
# extracted from it would print to the pipeline log unmasked.
if [[ ! "$AIVEN_DB_PASS" =~ ^[a-zA-Z0-9_.-]+$ ]]; then
    log_error "The password in AIVEN_CONNECTION_STRING contains characters outside [a-zA-Z0-9_.-], which --set-env-vars can't carry safely. Reset it in the Aiven console."
    exit 1
fi

# DB_SOCKET is set explicitly empty, not just omitted: a socket path left over in any .env the
# container carries would otherwise win, since Env::get() only falls back to .env when the real
# environment variable is absent -- an empty one wins and switches DB.php onto the host/port path.
export DB_ENV_VARS="DB_SOCKET=,DB_HOST=$AIVEN_DB_HOST,DB_PORT=$AIVEN_DB_PORT,DB_USER=$AIVEN_DB_USER,DB_PASS=$AIVEN_DB_PASS,DB_NAME=$AIVEN_DB_NAME,DB_SSL_CA=$AIVEN_CA_MOUNT_PATH"
DB_UPDATE_FLAGS=(--clear-cloudsql-instances --set-secrets="$AIVEN_CA_MOUNT_PATH=$AIVEN_CA_SECRET:latest")
DB_CREATE_FLAGS=(--set-secrets="$AIVEN_CA_MOUNT_PATH=$AIVEN_CA_SECRET:latest")
DB_REQUIRED_APIS=(secretmanager.googleapis.com)

db_provision() {
    log_info "DB_PROVIDER=aiven: the database is provisioned in the Aiven console, not by this toolkit."
    log_info "Checking the Aiven CA secret ($AIVEN_CA_SECRET) exists in Secret Manager..."
    if ! gcloud secrets describe "$AIVEN_CA_SECRET" &>/dev/null; then
        log_error "Secret Manager secret '$AIVEN_CA_SECRET' not found. Create it from the Aiven project's ca.pem first (see the header of deploy/gcp/db/aiven.sh)."
        exit 1
    fi
    log_success "Aiven CA secret found. Database: $AIVEN_DB_NAME on $AIVEN_DB_HOST:$AIVEN_DB_PORT."
}
