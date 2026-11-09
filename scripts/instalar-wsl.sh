#!/usr/bin/env bash
# Prepara WSL (Ubuntu 24.04 o Debian 12/13) para correr PlayBoss API:
# PHP 8.3 + extensiones (mysql, pgsql, sqlsrv, mongodb), Composer, Docker y Node.
# Si ya hay MySQL/MariaDB local, crea ahí la base dev_playboss y el usuario playboss.
# Uso: bash scripts/instalar-wsl.sh   (pide la clave de sudo)
set -euo pipefail

PHP_VERSION=8.3
DB_PASSWORD=PlayBoss_Local2026

paso() { printf '\n\033[1;33m==> %s\033[0m\n' "$1"; }

. /etc/os-release
case "$ID" in
    ubuntu|debian) ;;
    *) echo "Distro no soportada: $ID (usa Ubuntu o Debian)." >&2; exit 1 ;;
esac

if [ "$(ps -p 1 -o comm=)" != "systemd" ]; then
    echo "systemd no está activo en WSL. Agrega en /etc/wsl.conf:" >&2
    printf '[boot]\nsystemd=true\n' >&2
    echo "Luego en PowerShell: wsl --shutdown, vuelve a abrir la terminal y reintenta." >&2
    exit 1
fi

paso "Paquetes base"
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg unzip git lsb-release

paso "Repositorio de PHP ${PHP_VERSION} (trae ext-mongodb 2.x que exige mongodb/mongodb 2.5)"
if ! apt-cache policy "php${PHP_VERSION}-mongodb" | grep -q 'Candidate: [0-9]'; then
    if [ "$ID" = "ubuntu" ]; then
        sudo apt-get install -y software-properties-common
        sudo add-apt-repository -y ppa:ondrej/php
    else
        curl -fsSL https://packages.sury.org/php/apt.gpg | sudo gpg --dearmor --yes -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" \
            | sudo tee /etc/apt/sources.list.d/sury-php.list >/dev/null
    fi
    sudo apt-get update
fi
sudo apt-get install -y \
    php${PHP_VERSION}-cli php${PHP_VERSION}-dev php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-mysql php${PHP_VERSION}-pgsql php${PHP_VERSION}-mongodb php-pear unixodbc-dev

paso "Driver ODBC 18 de Microsoft (necesario para SQL Server)"
if ! dpkg -s msodbcsql18 >/dev/null 2>&1; then
    curl -fsSL -o /tmp/packages-microsoft-prod.deb \
        "https://packages.microsoft.com/config/${ID}/${VERSION_ID}/packages-microsoft-prod.deb"
    sudo dpkg -i /tmp/packages-microsoft-prod.deb
    rm /tmp/packages-microsoft-prod.deb
    sudo apt-get update
    sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18
fi

paso "Extensiones sqlsrv y pdo_sqlsrv (PECL)"
for ext in sqlsrv pdo_sqlsrv; do
    if [ ! -f "$(php-config${PHP_VERSION} --extension-dir)/${ext}.so" ]; then
        printf '\n' | sudo pecl -d php_suffix=${PHP_VERSION} install "$ext"
    fi
done
echo "extension=sqlsrv.so" | sudo tee /etc/php/${PHP_VERSION}/mods-available/sqlsrv.ini >/dev/null
printf '; priority=30\nextension=pdo_sqlsrv.so\n' | sudo tee /etc/php/${PHP_VERSION}/mods-available/pdo_sqlsrv.ini >/dev/null
sudo phpenmod -v ${PHP_VERSION} sqlsrv pdo_sqlsrv

paso "Composer"
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

paso "Docker Engine"
if ! command -v docker >/dev/null; then
    curl -fsSL https://get.docker.com | sudo sh
fi
sudo usermod -aG docker "$USER"
sudo systemctl enable --now docker

paso "Node.js 20 (para el front)"
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

paso "MySQL/MariaDB local"
if systemctl is-active --quiet mariadb || systemctl is-active --quiet mysql; then
    sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS dev_playboss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'playboss'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'playboss'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON dev_playboss.* TO 'playboss'@'localhost';
GRANT ALL PRIVILEGES ON dev_playboss.* TO 'playboss'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    echo "  Base dev_playboss y usuario playboss listos en el MySQL/MariaDB local."
    echo "  Levanta Docker SIN el perfil mysql: docker compose up -d --wait"
else
    echo "  No hay MySQL/MariaDB local: levanta Docker CON el perfil mysql:"
    echo "  docker compose --profile mysql up -d --wait"
fi

paso "Verificación"
php -v | head -1
for ext in pdo_mysql pdo_pgsql pdo_sqlsrv sqlsrv mongodb; do
    if php -m | grep -qx "$ext"; then echo "  [ok] $ext"; else echo "  [FALTA] $ext"; fi
done
composer --version
docker --version
node --version

printf '\n\033[1;32mListo.\033[0m Cierra y vuelve a abrir la terminal (para usar docker sin sudo)\n'
echo "y sigue con docs/SETUP-LOCAL.md."
