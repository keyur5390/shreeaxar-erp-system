#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || chmod -R 775 storage bootstrap/cache

if [ ! -f .env ]; then
  cp .env.example .env
fi

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
until php -r "
  try {
    new PDO(
      'mysql:host=${DB_HOST};port=${DB_PORT:-3306}',
      '${DB_USERNAME}',
      '${DB_PASSWORD}'
    );
    exit(0);
  } catch (Exception \$e) {
    exit(1);
  }
" 2>/dev/null; do
  sleep 2
done
echo "MySQL is ready."

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force --no-interaction
fi

php artisan storage:link --force --no-interaction 2>/dev/null || true
php artisan migrate --force --no-interaction

if [ "${RUN_SEED:-true}" = "true" ]; then
  php artisan db:seed --force --no-interaction || echo "Warning: db:seed failed (database may already be seeded)."
fi

exec "$@"
