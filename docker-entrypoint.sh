#!/bin/sh
set -e

# Ensure APP_KEY exists to prevent 500 error on initial boot
if [ -z "${APP_KEY}" ]; then
    echo "WARNING: APP_KEY environment variable is not set! Generating a fallback key..."
    export APP_KEY=$(php artisan key:generate --show)
fi

# Create SQLite database file if using sqlite and it does not exist
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    if [ ! -f /app/database/database.sqlite ]; then
        echo "Creating database.sqlite file..."
        touch /app/database/database.sqlite
    fi
    chown -R www-data:www-data /app/database
fi

# Ensure storage and bootstrap/cache permissions
echo "Setting storage permissions..."
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs \
         /app/bootstrap/cache

chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Run database migrations if configured
if [ "${SKIP_MIGRATIONS}" != "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Migration skipped or failed (check DB connection)"
fi

# Cache configuration, routes, and views for maximum performance
echo "Caching Laravel configuration, routes, and views..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Starting application server..."
exec "$@"
