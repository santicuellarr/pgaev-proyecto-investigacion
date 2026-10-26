#!/bin/sh
set -e
if [ -z "$APP_KEY" ]; then
  APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  export APP_KEY
fi
php artisan config:clear >/dev/null 2>&1 || true
php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan migrate --force
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
