# Despliegue en la nube

Cada motor vive en un servicio administrado gratuito; la API corre en Render (Docker) y el front en Vercel.
Firebase y Google Drive ya estaban en la nube y no cambian.

| Pieza | Servicio | Plan |
|---|---|---|
| SQL Server (usuarios) | Azure SQL Database | Oferta gratuita (Azure for Students) |
| MySQL (catálogo deportivo) | Aiven for MySQL | Free |
| PostgreSQL (cuentas, apuestas, pagos) | Neon | Free |
| MongoDB (mercados, reglas, auditoría) | MongoDB Atlas | M0 Free |
| API Laravel | Render (Docker) | Free (se duerme tras 15 min sin uso; el primer request tarda ~1 min) |
| Front React | Vercel | Hobby |

Todas las conexiones se anotan en `.env.nube` (copia de `.env.nube.example`, ignorado por git).

## 1. Crear las bases de datos

### SQL Server — Azure SQL
1. Activar **Azure for Students** con el correo de la universidad.
2. Portal de Azure → *Azure SQL* → *Crear* → **Base de datos SQL** → marcar **Aplicar oferta gratuita**.
3. Grupo de recursos `playboss`, base `playboss`, servidor nuevo `playboss-sql-<algo>` (región East US u otra cercana).
   Autenticación **SQL**: usuario `playbossadmin` y una contraseña fuerte.
4. Comportamiento al llegar al límite gratis: **Pausar automáticamente**.
5. Redes: **Punto de conexión público** y en el firewall agregar la regla `todo` de `0.0.0.0` a `255.255.255.255`
   (Render no tiene IP fija; para un entorno real se usaría una IP fija o red privada).
6. `.env.nube`: `SQLSERVER_HOST=<servidor>.database.windows.net`, usuario y contraseña.

### MySQL — Aiven
1. aiven.io → *Create service* → **MySQL** → plan **Free**.
2. En *Overview* copiar host, puerto, usuario (`avnadmin`), contraseña y base (`defaultdb`).
3. Descargar **CA certificate** y guardarlo en `storage/app/private/nube/aiven-ca.pem`.

### PostgreSQL — Neon
1. neon.tech → proyecto `playboss` → base `playboss`.
2. *Connect* → *Parameters only*: host, base, usuario y contraseña. `POSTGRES_SSLMODE=require`.

### MongoDB — Atlas
1. Crear clúster **M0 Free**.
2. *Database Access*: usuario `playboss` con contraseña (rol *Read and write to any database*).
3. *Network Access*: `0.0.0.0/0` (por la misma razón que Azure).
4. *Connect* → *Drivers*: copiar la cadena `mongodb+srv://...` en `MONGODB_URI`.

## 2. Crear las tablas y copiar los datos

Desde el PC, con las bases locales encendidas:

```bash
php artisan playboss:db-check --env=nube        # las 4 conexiones de la nube deben salir "conectada"
php artisan playboss:migrate --env=nube         # crea tablas, colecciones e índices en la nube
php artisan playboss:copiar-a-nube              # copia todos los datos locales conservando los ids
```

`playboss:copiar-a-nube` acepta un motor (`sqlsrv`, `mysql`, `pgsql`, `mongodb`) y `--forzar` para reemplazar
sin preguntar. Los contadores de ids (IDENTITY de SQL Server, secuencias de PostgreSQL) quedan después del último id copiado.

## 3. API en Render

1. render.com → *New* → **Web Service** → *Public Git Repository*:
   `https://github.com/DanielMolina6584/playboss-casino-api` (rama `master`).
2. Runtime **Docker**, plan **Free**, nombre `playboss-api`.
3. *Environment*: pegar las variables de `.env.nube` (botón *Add from .env*), cambiando las rutas de archivos:
   - `FIREBASE_CREDENTIALS=/etc/secrets/firebase-service-account.json`
   - `GOOGLE_DRIVE_CREDENTIALS=/etc/secrets/drive-oauth-client.json`
   - `MYSQL_ATTR_SSL_CA=/etc/secrets/aiven-ca.pem`
4. *Secret Files*: subir esos 3 archivos con esos nombres (contenido de `storage/app/private/...`).
5. Al terminar el deploy: `https://playboss-api.onrender.com/api/documentation`.

## 4. Front en Vercel

Se compila en el PC (así se incluyen los escudos, que no están en git) y se sube la carpeta `dist`:

```bash
cd ~/proyectos/playboss-casino
VITE_API_URL=https://playboss-api.onrender.com/api VITE_DATA_MODE=api npm run build
npx vercel deploy dist --prod
```

Luego, en Render, `CORS_ALLOWED_ORIGINS` y `WOMPI_REDIRECT_URL` deben apuntar a la URL que dio Vercel.

## Mantenimiento

- **Token de Drive**: mientras la app de OAuth esté en modo *Testing*, el refresh token vence cada 7 días.
  Correr `php artisan playboss:drive-token` en el PC y copiar el nuevo `GOOGLE_DRIVE_REFRESH_TOKEN` en Render.
- **Volver a copiar los datos** después de cambiar los locales: `php artisan playboss:copiar-a-nube --forzar`.
- **Logs de la API**: pestaña *Logs* de Render (los errores se envían a stderr).
