#!/bin/sh
set -e

echo "🚀 Starting CanchApp..."

# Create log directories
mkdir -p /var/log/supervisor
mkdir -p /var/log/nginx

# Wait for MySQL to be ready
echo "⏳ Waiting for database..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  echo "   Database not ready, retrying in 3s..."
  sleep 3
done
echo "✅ Database connected!"

# Go to app directory
cd /var/www/html

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
  echo "🔑 Generating APP_KEY..."
  php artisan key:generate --force
fi

# Clear and cache config for production
echo "⚙️  Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations
echo "🗃️  Running migrations..."
php artisan migrate --force --no-interaction

# Create storage symlink
echo "🔗 Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ CanchApp ready!"

exec "$@"
