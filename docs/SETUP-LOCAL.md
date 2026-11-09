# Entorno local con WSL + Docker

Guía para dejar PlayBoss API corriendo en Windows + WSL (Ubuntu 24.04 o Debian 12/13).
PostgreSQL, SQL Server y MongoDB corren en Docker; MySQL usa el MySQL/MariaDB del equipo si existe (si no, también va en Docker). Firebase Firestore y Google Drive son servicios en la nube de Google.

| Motor              | Conexión Laravel | Puerto | Base           | Usuario    | Clave                |
|--------------------|------------------|--------|----------------|------------|----------------------|
| MySQL / MariaDB    | `mysql`          | 3306   | `dev_playboss` | `playboss` | `PlayBoss_Local2026` |
| PostgreSQL 16      | `pgsql`          | 5432   | `playboss`     | `playboss` | `PlayBoss_Local2026` |
| SQL Server 2022    | `sqlsrv`         | 1433   | `playboss`     | `sa`       | `PlayBoss_Local2026` |
| MongoDB 7          | `mongodb`        | 27017  | `playboss`     | `playboss` | `PlayBoss_Local2026` |
| Firebase Firestore | `Firestore`      | nube   | `(default)`    | cuenta de servicio | JSON en `storage/app/private/firebase/` |
| Google Drive       | `Drive`          | nube   | carpeta        | OAuth (Gmail) | JSON en `storage/app/private/google-drive/` + refresh token |

> Credenciales locales solo para desarrollo. Los JSON de Google **nunca** se suben a Git (la carpeta `storage/app/private` ya está ignorada).

## 1. WSL (solo si el equipo no lo tiene)

En PowerShell como administrador:

```powershell
wsl --install -d Ubuntu-24.04
```

## 2. Instalar PHP, extensiones, Composer, Docker y Node

Desde la carpeta del proyecto, en **tu terminal** (pide la clave de sudo):

```bash
bash scripts/instalar-wsl.sh
```

Al final debe mostrar `[ok]` en `pdo_mysql`, `pdo_pgsql`, `pdo_sqlsrv`, `sqlsrv` y `mongodb`.
Si había MySQL/MariaDB, el script crea la base `dev_playboss` y el usuario `playboss`.
**Cierra y vuelve a abrir la terminal** para usar `docker` sin `sudo`.

## 3. Levantar las bases de datos en Docker

```bash
docker compose up -d --wait                  # si el equipo YA tiene MySQL/MariaDB
docker compose --profile mysql up -d --wait  # si NO tiene MySQL/MariaDB
docker compose ps
```

Todas deben salir `healthy`. `playboss-sqlserver-init` sale `exited (0)`: es normal, solo crea la base `playboss` en SQL Server.

## 4. Configurar Laravel

```bash
composer install
cp .env.docker.example .env      # si aún no existe
php artisan key:generate
```

## 5. Firebase Firestore

1. En https://console.firebase.google.com, proyecto **playboss-7d872** → **Firestore Database** → *Crear base de datos* (si no existe).
2. ⚙ **Configuración del proyecto → Cuentas de servicio → Generar nueva clave privada**. Se descarga un JSON.
3. Guárdalo como `storage/app/private/firebase/service-account.json`.
4. En `.env` (ya viene así en `.env.docker.example`):
   ```
   FIREBASE_CREDENTIALS=storage/app/private/firebase/service-account.json
   FIREBASE_PROJECT_ID=playboss-7d872
   FIREBASE_FIRESTORE_DATABASE=(default)
   ```

> La configuración web (`apiKey`, `authDomain`, `appId`…) es para el **front** con el SDK de JavaScript. El backend no la usa: necesita la cuenta de servicio.

## 6. Google Drive

Hay dos modos según dónde esté la carpeta. El ID de la carpeta es lo que va después de `/folders/` en la URL.

> Las cuentas de servicio **no tienen espacio propio** en Drive: en el "Mi unidad" de un Gmail personal la subida falla con *"Service Accounts do not have storage quota"*. Por eso con Gmail personal se usa OAuth.

### 6A. Gmail personal (OAuth) — el que usa este proyecto

1. En https://console.cloud.google.com selecciona el proyecto **playboss-7d872** (es el mismo de Firebase).
2. **APIs y servicios → Biblioteca → Google Drive API → Habilitar**.
3. **APIs y servicios → Pantalla de consentimiento de OAuth** (o *Google Auth Platform*):
   - Tipo de usuario **Externo**, nombre de la app `PlayBoss`, tu correo de soporte.
   - En **Público / Audience** pulsa **Publicar app** (pasa a *En producción*). Si queda en *Prueba*, el token se vence a los 7 días.
4. **Credenciales → Crear credenciales → ID de cliente de OAuth → Tipo: App de escritorio**. Descarga el JSON.
5. Guárdalo como `storage/app/private/google-drive/oauth-client.json`.
6. En `.env`:
   ```
   GOOGLE_DRIVE_CREDENTIALS=storage/app/private/google-drive/oauth-client.json
   GOOGLE_DRIVE_FOLDER_ID=<id de la carpeta>
   ```
7. Ejecuta y sigue las instrucciones (inicia sesión con la cuenta **dueña de la carpeta**):
   ```bash
   php artisan playboss:drive-token
   ```
   Google mostrará *"Google no verificó esta app"*: **Configuración avanzada → Ir a PlayBoss**. Al final el navegador cae en una página de error de `localhost`: copia esa URL completa y pégala en la terminal.
8. Copia en `.env` la línea `GOOGLE_DRIVE_REFRESH_TOKEN=...` que imprime el comando.

### 6B. Unidad compartida de Google Workspace (cuenta de servicio)

1. Habilita la Google Drive API (paso 6A.2).
2. Guarda el JSON de la cuenta de servicio (puede ser el de Firebase) como `storage/app/private/google-drive/service-account.json`.
3. Comparte la carpeta (dentro de una **Unidad compartida**) como **Editor** con el `client_email` del JSON.
4. En `.env`: `GOOGLE_DRIVE_CREDENTIALS=storage/app/private/google-drive/service-account.json`, `GOOGLE_DRIVE_FOLDER_ID`, `GOOGLE_DRIVE_SHARED_DRIVE_ID` y `GOOGLE_DRIVE_REFRESH_TOKEN` vacío.

## 7. Probar todas las conexiones

```bash
php artisan playboss:db-check      # sqlsrv, mysql, pgsql, mongodb
php artisan playboss:cloud-check   # firestore, drive
```

Todo debe salir `conectada`. `cloud-check drive` además sube y borra un archivo de prueba para validar permisos y espacio.

## 8. Crear las tablas

```bash
php artisan playboss:migrate
```

## 9. Levantar la API

```bash
php artisan serve
```

- API: http://localhost:8000/api
- Swagger: http://localhost:8000/api/documentation

## 10. Crear un usuario administrador

No hay seeder de admin. Regístrate normal (`POST /api/auth/registro` o desde el front) y luego promuévelo:

```bash
docker exec -it playboss-sqlserver /opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa -P 'PlayBoss_Local2026' -d playboss \
  -Q "UPDATE u SET rol_codigo = 'ADMIN' FROM usuario u JOIN usuario_credencial c ON c.usuario_id = u.id_usuario WHERE c.correo = 'TU_CORREO'"
```

## Problemas comunes

| Síntoma | Solución |
|---------|----------|
| `composer install` pide `ext-mongodb` | Falta la extensión: repite el paso 2 y revisa `php -m \| grep mongodb` |
| `composer install` se corta por timeout en `/mnt/c` | `COMPOSER_PROCESS_TIMEOUT=0 composer install` (o trabaja el proyecto dentro de `~/`) |
| `sqlsrv ... sin conexión` | `docker compose logs sqlserver`; tarda ~30 s la primera vez. Revisa `SQLSERVER_TRUST_SERVER_CERTIFICATE=true` |
| `mysql ... sin conexión` | ¿Corre MariaDB (`systemctl status mariadb`) o levantaste el perfil `mysql`? No ambos: chocan en el 3306 |
| `permission denied ... docker.sock` | Cierra y abre la terminal tras el paso 2 (o `newgrp docker`) |
| Firestore `HTTP 404 ... database (default) does not exist` | Falta crear Firestore en la consola de Firebase (paso 5.1) |
| Firestore/Drive `HTTP 403 ... API has not been used` | Habilita la API (Cloud Firestore API / Google Drive API) en Google Cloud |
| Drive `HTTP 404 File not found` | El ID de carpeta está mal, o (modo 6B) no está compartida con el `client_email` |
| Drive `storage quota` | Gmail personal con cuenta de servicio: usa el modo OAuth (paso 6A) |
| Drive `invalid_grant` | El refresh token venció o se revocó: repite `php artisan playboss:drive-token` (y publica la app, paso 6A.3) |

## Comandos útiles

```bash
docker compose stop                      # apagar (conserva datos)
docker compose up -d --wait              # volver a encender
docker compose down -v                   # BORRAR todo y empezar de cero (luego repetir paso 8)
php artisan playboss:db-check mysql      # probar una sola conexión
php artisan playboss:cloud-check drive   # probar solo Drive
php artisan playboss:migrate pgsql       # migrar una sola conexión
```
