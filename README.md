# playboss-api

API REST en Laravel 12 para PlayBoss (plataforma de apuestas deportivas). Expone autenticación de usuarios (Sanctum) y, a futuro, el resto del dominio: catálogo deportivo, apuestas, cuenta/billetera y transacciones.

## Stack

- PHP 8.2+ / Laravel 12
- MySQL 8+ o Microsoft SQL Server 2016+ (esquema de 33 tablas)
- Laravel Sanctum (tokens de acceso para el front)

## Arquitectura / convenciones

Sigue el mismo patrón que otros backends del equipo (ver `tiendas-virtuales-back`):

- `app/Http/Controllers/Api/Controller.php`: controller base con un contrato de respuesta único — `{ error, mensaje, data }` — y helpers (`sendResponse()`, `agregarError()`, `setDataResponse()`, `validateRequestRules()`). Todos los controllers de API extienden de aquí.
- `app/Service/SvcXxx.php`: la lógica de negocio y el acceso a modelos vive en Services con prefijo `Svc` (no en los controllers). Los métodos que tocan base de datos van con `try/catch` + log.
- `app/Models/<Dominio>/`: modelos Eloquent agrupados por dominio (ej. `app/Models/Usuario/Usuario.php`).
- `usuario`, `usuario_credencial` y `usuario_perfil` separan la cuenta, las credenciales de acceso y los datos personales/documento, respectivamente. La tabla `logs` conserva la auditoría de accesos.
- `database/sql/playboss-schema.sql` y `database/sql/playboss-schema-sqlserver.sql`: DDL equivalente para MySQL y SQL Server. La migración `create_playboss_schema_from_sql` selecciona el archivo según el driver de Laravel.
- `routes/api.php`: rutas agrupadas por prefijo (`Route::prefix('auth')->group(...)`).

## Requisitos

- PHP 8.2+, Composer
- MySQL 8+ con una base de datos ya creada (por defecto `playboss`)

## Puesta en marcha

```bash
composer install
cp .env.example .env
# Editar .env: APP_URL y DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# APP_URL=http://localhost/playboss-casino-api/public
php artisan key:generate

php artisan migrate   # crea el esquema y las tablas requeridas por Laravel

# Dar permisos de escritura a Apache en los directorios que Laravel necesita
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Con el proyecto dentro de `/var/www/html`, Apache lo sirve directamente desde `public/` en:

- API: `http://localhost/playboss-casino-api/public/api`
- Health check: `http://localhost/playboss-casino-api/public/up`

No es necesario ejecutar `php artisan serve` ni crear un vhost adicional. Apache debe tener habilitado `mod_rewrite` y permitir `.htaccess` en `/var/www/html`; en esta instalación `mod_rewrite` ya está activo.

## Endpoints actuales

```
POST /api/auth/registro   — crea un usuario y devuelve token
POST /api/auth/login      — valida credenciales y devuelve token
POST /api/auth/logout     — revoca el token actual        (requiere Bearer token)
GET  /api/auth/me         — datos del usuario autenticado  (requiere Bearer token)
```

Cada intento de login/registro asociado a un usuario queda auditado en `logs` (ip, dispositivo, éxito/fallo y el id del token emitido en `personal_access_tokens`).

## Base de datos

El esquema y el conjunto completo de datos se aplican con `php artisan migrate` usando el driver configurado. La migración carga un único `.sql` por motor: incluye los datos de Liga BetPlay y los datos de prueba de las demás áreas. También puedes ejecutar directamente el DDL y después la carga completa en una base vacía:

| Motor | Crear tablas | Carga completa (Liga + datos demo) |
|---|---|---|
| MySQL 8+ | `database/sql/playboss-schema.sql` | `database/sql/data/playboss-complete-demo-mysql.sql` |
| SQL Server 2016+ | `database/sql/playboss-schema-sqlserver.sql` | `database/sql/data/playboss-complete-demo-sqlserver.sql` |

La migración `2026_09_30_000001_load_liga_colombiana_2026_from_sql` selecciona y ejecuta el archivo completo según `DB_CONNECTION`. Para una base nueva, ejecuta `php artisan migrate`; no se ejecuta `db:seed`. La carga reemplaza equipos, estadios, jugadores, temporadas, jornadas, partidos, alineaciones y cuotas. Incluye además 300 usuarios con credenciales/perfil/cuenta, 600 logs de acceso, 300 códigos de recuperación, 300 partidos demo, 900 cuotas, 6.600 alineaciones, 300 apuestas/detalles, 600 transacciones y 600 logs de pago.

La carga está pensada para una base nueva o de pruebas y se ejecuta una sola vez. Se detiene si hay apuestas existentes o si ya encuentra cuentas demo, para no borrar apuestas ni duplicar usuarios. Los 300 partidos, resultados, cuotas, alineaciones, apuestas, pagos y cuentas demo son **ficticios**: solo sirven para probar las funciones de la aplicación y no representan eventos ni operaciones reales. No usar estos datos en producción.

```bash
# MySQL (base playboss ya creada; ejecutar desde la raíz del proyecto)
mysql -u root -p playboss < database/sql/playboss-schema.sql
mysql -u root -p playboss < database/sql/data/playboss-complete-demo-mysql.sql

# SQL Server: ejecuta playboss-schema-sqlserver.sql y luego
# data/playboss-complete-demo-sqlserver.sql desde Management Studio o sqlcmd.
```

El bloque de Liga contiene los 20 clubes de Primera A y 670 jugadores activos listados por ESPN para 2026, distribuidos según su plantilla; los clubes participantes se contrastaron con DIMAYOR. ESPN es una fuente secundaria, no un registro oficial de plantillas. Los datos biográficos ausentes (fecha para 53 jugadores, nacionalidad para 46, posición para 3, dorsal para 20 y apellido para 4) quedan como `NULL`. Las capacidades de estadio no se verificaron y también quedan `NULL`. Las fechas de temporadas son ventanas administrativas, no calendario oficial. Los fixtures oficiales consultados no permitieron validar todas las fechas y horarios: por eso los 300 partidos cargados son fixtures de prueba inventados, claramente separados de la información real de clubes/plantillas y sin afirmar que sean partidos oficiales.

Las 300 cuentas de prueba usan `demo001@playboss.test` hasta `demo300@playboss.test`; todas comparten la contraseña `Demo12345!`. Los saldos y pagos son ficticios y no conectan con pasarelas reales.

Fuentes: [DIMAYOR — fixture Liga BetPlay I-2026](https://dimayor.com.co/2025/12/18/fixture-de-las-primeras-19-fechas-de-la-liga-betplay-dimayor-i-2026/), [DIMAYOR — fixture Liga BetPlay II-2026](https://dimayor.com.co/2026/06/03/fixture-de-la-fase-l-todos-contra-todos-de-la-liga-betplay-dimayor-ll-2026/) y [API pública ESPN — Primera A 2026](https://site.api.espn.com/apis/site/v2/sports/soccer/col.1/teams?limit=100&season=2026). Los scripts incluyen el enlace al roster usado para cada equipo.

Para usar SQL Server como conexión principal de Laravel, configura `DB_CONNECTION=sqlsrv` y completa `SQLSERVER_HOST`, `SQLSERVER_PORT`, `SQLSERVER_DATABASE`, `SQLSERVER_USERNAME` y `SQLSERVER_PASSWORD`; también necesitas `pdo_sqlsrv` instalado en PHP. PostgreSQL se selecciona con `DB_CONNECTION=pgsql` y sus variables `POSTGRES_*`. El DDL y la carga SQL de SQL Server se ejecutan con SQL Server Management Studio o `sqlcmd`.

## Conexiones externas

La conexión principal sigue siendo MySQL (`DB_CONNECTION=mysql`). Laravel también tiene conexiones secundarias independientes para SQL Server (`sqlsrv`), PostgreSQL (`pgsql`) y MongoDB (`mongodb`). Sus hosts, bases de datos y usuarios se configuran por separado en `.env` con los prefijos `SQLSERVER_`, `POSTGRES_`, `MYSQL_` y `MONGODB_`. Ejemplos de uso:

```php
DB::connection('sqlsrv')->select('SELECT 1');
DB::connection('pgsql')->select('SELECT 1');
DB::connection('mongodb')->collection('ejemplo')->get();
```

Firestore se configura con `FIREBASE_PROJECT_ID`, `FIREBASE_FIRESTORE_DATABASE` y la ruta privada `FIREBASE_CREDENTIALS`; el cliente REST se puede inyectar como `Google\Service\Firestore`. Google Drive usa `GOOGLE_DRIVE_CREDENTIALS` y un folder ID para ubicar archivos. Comparte la carpeta de destino con la cuenta de servicio de Google Drive cuando recibas esas credenciales. Los JSON de credenciales deben guardarse dentro de `storage/app/private/credentials/`, directorio excluido de Git.

Los SDK de MongoDB y Google ya están declarados en Composer. MongoDB requiere la extensión PHP `mongodb`; PostgreSQL necesita `pdo_pgsql`, y SQL Server `pdo_sqlsrv`. Firestore usa el API REST de Google y no requiere la extensión gRPC. El host de desarrollo todavía no tiene habilitadas `mongodb` ni `pdo_sqlsrv`; además, la instalación del paquete PHP de PostgreSQL requiere completar su activación en la configuración PHP local. Las conexiones quedan configuradas, pero esos drivers deben estar activos antes de abrir conexiones a esos motores.

## Pendiente (próximas entregas)

Apuestas y transacciones reales, verificar las plantillas con registros oficiales de DIMAYOR, completar capacidades de estadios y sustituir los fixtures ficticios cuando se publiquen las fechas y horarios oficiales.
