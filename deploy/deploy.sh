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
# O npm do Windows remove do lock dependências opcionais só de Linux/wasm;
# se o lock vier assim, instala sem gravá-lo (o git pull seguinte continua limpo).
if ! npm ci --no-audit --no-fund; then
    echo "package-lock.json fora de sincronia para Linux; usando npm install --no-save" >&2
    npm install --no-audit --no-fund --no-save
fi
npm run build
$PHP artisan migrate --force
$PHP artisan optimize
# Captura antes do grep: com pipefail, o grep -q pode encerrar o artisan por SIGPIPE.
ARTISAN_COMMANDS=$($PHP artisan list --raw)
if grep -q '^filament:optimize' <<<"$ARTISAN_COMMANDS"; then
    $PHP artisan filament:optimize
fi
chown -R www-data:www-data storage bootstrap/cache
systemctl restart comanda-reverb
echo "Deploy concluído: $(git log -1 --format='%h %s')"
