#!/bin/sh
set -e

echo "🔌 Starting CanchApp Reverb WebSocket Server..."

# Wait for MySQL to be ready
echo "⏳ Waiting for database..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  echo "   Database not ready, retrying in 3s..."
  sleep 3
done
echo "✅ Database connected!"

cd /var/www/html

# Cache config
php artisan config:cache

echo "✅ Reverb ready on port 8080!"

exec "$@"
