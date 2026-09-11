#!/bin/sh
set -e

# -----------------------------------------------------------------------------
# Container start-up for DKM Erdigma
#
# Waits for MySQL, applies migrations, then warms Laravel's caches. Caching is
# done here rather than at build time because the config cache bakes in
# environment values, which are only known once the container runs.
# -----------------------------------------------------------------------------

echo "[entrypoint] Menunggu database ${DB_HOST}:${DB_PORT} siap..."

attempt=0
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    attempt=$((attempt + 1))

    if [ "$attempt" -ge 60 ]; then
        echo "[entrypoint] Database tidak merespons setelah 60 percobaan. Berhenti." >&2
        exit 1
    fi

    sleep 2
done

echo "[entrypoint] Database siap."

if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] APP_KEY kosong. Isi variabel ini sebelum menjalankan container." >&2
    exit 1
fi

echo "[entrypoint] Menjalankan migrasi..."
php artisan migrate --force

echo "[entrypoint] Menyiapkan data awal yang belum ada..."
php artisan db:seed --force --class=Database\\Seeders\\RoleSeeder
php artisan db:seed --force --class=Database\\Seeders\\SettingSeeder

echo "[entrypoint] Menyusun cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[entrypoint] Siap. Menyalakan layanan."

exec "$@"
