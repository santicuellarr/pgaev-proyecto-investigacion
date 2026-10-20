#!/bin/sh
set -e
php artisan config:clear >/dev/null 2>&1 || true
php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan migrate --force
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
