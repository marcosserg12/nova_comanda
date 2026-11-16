#!/usr/bin/env bash
# Publica a versão mais recente do main.
# Uso (do seu PC): ssh root@145.223.26.225 'bash /var/www/comanda/deploy/deploy.sh'
set -euo pipefail

APP_DIR=/var/www/comanda
PHP=/usr/bin/php8.3
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
$PHP artisan migrate --force
$PHP artisan optimize
if $PHP artisan list --raw | grep -q '^filament:optimize'; then
    $PHP artisan filament:optimize
fi
chown -R www-data:www-data storage bootstrap/cache
systemctl restart comanda-reverb
echo "Deploy concluído: $(git log -1 --format='%h %s')"
