# Imagen de producción de PlayBoss API (Render u otro hosting con Docker).
# PHP 8.3 + Apache con los drivers de los 4 motores: SQL Server, MySQL, PostgreSQL y MongoDB.
FROM php:8.3-apache-bookworm

# Driver ODBC de Microsoft (SQL Server) + librerías de compilación de las extensiones
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl gnupg2 ca-certificates unzip libpq-dev libzip-dev unixodbc-dev libssl-dev \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip opcache \
    && pecl install sqlsrv pdo_sqlsrv mongodb \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv mongodb \
    && apt-get purge -y --auto-remove gnupg2 \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Apache sirve /public y respeta el .htaccess de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "upload_max_filesize=5M\npost_max_size=8M\nexpose_php=Off\n" > "$PHP_INI_DIR/conf.d/playboss.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Dependencias primero (capa cacheable), luego el código
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY scripts/docker-entrypoint.sh /usr/local/bin/playboss-entrypoint
RUN chmod +x /usr/local/bin/playboss-entrypoint

ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["playboss-entrypoint"]
CMD ["apache2-foreground"]
