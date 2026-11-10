#!/bin/sh
# Arranque del contenedor: Apache escucha en $PORT (Render lo define) y se cachea la configuración.
set -e

sed -ri "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf

# Render deja los Secret Files en /etc/secrets solo legibles por root, pero Apache corre como
# www-data: se copian a una carpeta privada de www-data y las variables apuntan a la copia.
if [ -d /etc/secrets ]; then
    mkdir -p /var/www/secrets
    cp /etc/secrets/* /var/www/secrets/ 2>/dev/null || true
    chown -R www-data:www-data /var/www/secrets
    chmod 700 /var/www/secrets
    chmod 400 /var/www/secrets/* 2>/dev/null || true
    for var in FIREBASE_CREDENTIALS GOOGLE_DRIVE_CREDENTIALS MYSQL_ATTR_SSL_CA; do
        eval "valor=\${$var:-}"
        case "$valor" in
            /etc/secrets/*) export "$var=/var/www/secrets/${valor#/etc/secrets/}" ;;
        esac
    done
fi

# Los logs van a la salida estándar para verlos en el panel del hosting
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

php artisan config:cache
php artisan route:cache

exec "$@"
