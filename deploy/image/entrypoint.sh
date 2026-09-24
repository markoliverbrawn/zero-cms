#!/bin/bash
set -e

# ZERO CMS - CONTAINER RUNTIME ENTRYPOINT
# Dynamically writes Cloud Run environment variables to a physical .env file
# before spawning Apache. Under this image's mod_php, Apache inherits the
# container environment and Env::get() reads it via getenv() first, so the
# .env copy is a fallback rather than the primary delivery path -- it keeps
# the whitelisted variables available to code that reads .env directly, and
# to SAPIs that really do scrub the environment (e.g. php-fpm with
# clear_env=yes).

echo "----------------------------------------------------------------"
echo "ZERO CMS: Compiling runtime environment variables..."
echo "----------------------------------------------------------------"

# Write safe prefix-filtered variables to .env. Includes the queue/scheduler trigger tokens (read
# by QueueApiController/SchedulerApiController to authorize Cloud Scheduler's HTTP calls) and the
# optional GOOGLE_/AWS_/SMTP_/APP_KEY integrations -- without these, Cloud Scheduler's requests into
# /api/v1/queue/process and /api/v1/queue/schedule fail with a "must be defined inside .env" error.
echo "# Generated dynamically at container boot" > /var/www/html/.env
env | grep -E '^(DB_|GCS_|STORAGE_|ENVIRONMENT|BASE_|ADMIN_|APP_KEY|QUEUE_TRIGGER_TOKEN|SCHEDULER_TRIGGER_TOKEN|TRUSTED_PROXY_SECRET|GOOGLE_|AWS_|SMTP_|BENCHMARKING)=' >> /var/www/html/.env || true

# Ensure proper ownership and permissions are restricted to the Apache runtime user ONLY
chown www-data:www-data /var/www/html/.env
chmod 0600 /var/www/html/.env

echo "Successfully wrote runtime .env configuration."
echo "Launching Apache..."
echo "----------------------------------------------------------------"

# Pass control to standard Apache foreground service
exec "$@"
