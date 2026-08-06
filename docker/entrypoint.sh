#!/bin/sh
set -e

if [ ! -d vendor ]; then
    composer install
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --ansi
fi

if [ ! -f public/build/manifest.json ]; then
    npm ci
    npm run build
fi

exec php artisan serve --host=0.0.0.0 --port=8000
