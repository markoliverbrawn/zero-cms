#!/bin/sh
set -e

# The source tree is bind-mounted from the host, so build-time permissions
# don't apply to it - make sure the webserver can write logs/uploads/media.
mkdir -p storage/logs storage/private public/storage/uploads
chmod -R 777 storage public/storage 2>/dev/null || true

# Works whether this project *is* Zero CMS Core (no vendor/ needed - it
# self-autoloads via src/Core/Autoloader.php) or is a separate host project
# that requires markoliverbrawn/zero-cms-core via Composer (bin/create-project
# scaffolds public/index.php to boot from vendor/.../src/Core/Autoloader.php
# instead). In the latter case vendor/ is normally installed on the host
# before the bind mount ever reaches this container, but install it here too
# if it's missing (e.g. a fresh clone that gitignores vendor/).
if [ -f composer.json ] && [ ! -d vendor ]; then
    echo "vendor/ missing - running composer install..."
    composer install --no-interaction --no-progress --optimize-autoloader
fi

echo "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until php -r '
$h = getenv("DB_HOST") ?: "mysql";
$p = getenv("DB_PORT") ?: "3306";
try {
    new PDO("mysql:host={$h};port={$p}", getenv("DB_USER"), getenv("DB_PASS"));
} catch (\Throwable $e) {
    exit(1);
}
' 2>/dev/null; do
    sleep 2
done
echo "Database is up."

# bin/seed (Zero\Support\SeederRunner) already runs a full migration
# down()+up() itself before seeding, so it's the only step needed here -
# a separate bin/migrate call would be redundant, and isn't even present
# in host projects scaffolded via bin/create-project (only bin/seed/bin/test
# are copied there; bin/migrate is Core-repo-only tooling). Only run it once:
# re-running on every restart would destroy any data added after first boot.
# Delete storage/private/.provisioned (and the mysql_data volume, for a full
# reset) to force this to run again.
SENTINEL="storage/private/.provisioned"
if [ ! -f "$SENTINEL" ]; then
    echo "First boot: running bin/seed (migrates + seeds the default site)..."
    php bin/seed
    touch "$SENTINEL"
else
    echo "Already provisioned, skipping seed."
fi

exec "$@"
