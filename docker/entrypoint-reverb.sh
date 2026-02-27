#!/bin/sh
set -e

echo "🔌 Starting CanchApp Reverb WebSocket Server..."

# Show DB config for debugging (no password)
echo "📋 DB Config: host=${DB_HOST} port=${DB_PORT:-3306} db=${DB_DATABASE} user=${DB_USERNAME}"

# Wait for MySQL to be ready (max 120 seconds)
echo "⏳ Waiting for database..."
DB_RETRIES=0
DB_MAX_RETRIES=40
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/tmp/db_error.txt; do
  DB_RETRIES=$((DB_RETRIES + 1))
  if [ "$DB_RETRIES" -ge "$DB_MAX_RETRIES" ]; then
    echo "❌ Could not connect to database after ${DB_MAX_RETRIES} attempts."
    echo "   Last error: $(cat /tmp/db_error.txt)"
    exit 1
  fi
  echo "   Database not ready (attempt ${DB_RETRIES}/${DB_MAX_RETRIES}), retrying in 3s..."
  echo "   Error: $(cat /tmp/db_error.txt)"
  sleep 3
done
echo "✅ Database connected!"

cd /var/www/html

# Cache config
php artisan config:cache

echo "✅ Reverb ready on port 8080!"

exec "$@"
