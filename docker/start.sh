#!/bin/sh
set -e

# Render sets $PORT dynamically - default to 10000 if not set
PORT="${PORT:-10000}"
sed -i "s/PORT_PLACEHOLDER/${PORT}/" /etc/nginx/nginx.conf

# Generate app key if missing (safe no-op if already set)
php artisan key:generate --force || true

# Clear any cached config that might have stale DB settings
php artisan config:clear 2>&1 || true

# Run database migrations FIRST (before caching config)
php artisan migrate --force 2>&1 || true

# Cache config/routes/views for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ensure storage/bootstrap dirs are owned by the php-fpm user
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Start php-fpm + nginx via supervisor
exec supervisord -c /etc/supervisord.conf
