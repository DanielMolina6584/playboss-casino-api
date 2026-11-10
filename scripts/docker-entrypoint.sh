#!/bin/sh
# Arranque del contenedor: Apache escucha en $PORT (Render lo define) y se cachea la configuración.
set -e

sed -ri "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf

# Los logs van a la salida estándar para verlos en el panel del hosting
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

php artisan config:cache
php artisan route:cache

exec "$@"
