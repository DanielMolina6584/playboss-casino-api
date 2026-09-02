# playboss-api

API REST en Laravel 12 para PlayBoss (plataforma de apuestas deportivas). Expone autenticación de usuarios (Sanctum) y, a futuro, el resto del dominio: catálogo deportivo, apuestas, cuenta/billetera y transacciones.

## Stack

- PHP 8.2+ / Laravel 12
- MySQL (base de datos `playboss`, esquema ya modelado en 32 tablas)
- Laravel Sanctum (tokens de acceso para el front)

## Arquitectura / convenciones

Sigue el mismo patrón que otros backends del equipo (ver `tiendas-virtuales-back`):

- `app/Http/Controllers/Api/Controller.php`: controller base con un contrato de respuesta único — `{ error, mensaje, data }` — y helpers (`sendResponse()`, `agregarError()`, `setDataResponse()`, `validateRequestRules()`). Todos los controllers de API extienden de aquí.
- `app/Service/SvcXxx.php`: la lógica de negocio y el acceso a modelos vive en Services con prefijo `Svc` (no en los controllers). Los métodos que tocan base de datos van con `try/catch` + log.
- `app/Models/<Dominio>/`: un modelo Eloquent por tabla, agrupados por dominio (ej. `app/Models/Usuario/Usuario.php`).
- `database/sql/playboss-schema.sql`: **fuente de verdad del esquema de negocio** (32 tablas). Se versiona junto con el código y se ejecuta desde una migración (`create_playboss_schema_from_sql`) — no se edita a mano tabla por tabla salvo cambios puntuales vía migraciones complementarias.
- `routes/api.php`: rutas agrupadas por prefijo (`Route::prefix('auth')->group(...)`).

## Requisitos

- PHP 8.2+, Composer
- MySQL 8+ con una base de datos ya creada (por defecto `playboss`)

## Puesta en marcha

```bash
composer install
cp .env.example .env
# Editar .env: DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD con tus credenciales reales
php artisan key:generate

php artisan migrate   # crea solo lo que falte (Sanctum, etc.) — el esquema de negocio ya existente no se toca
php artisan db:seed   # siembra catálogos base: tipo_documento, rol, estado_usuario

php artisan serve --host=127.0.0.1 --port=8811
```

La API queda en `http://127.0.0.1:8811/api`.

> También hay un vhost de Apache apuntando a `public/`, pero requiere que el usuario del servidor web tenga permisos de lectura sobre el proyecto (pendiente de ajustar en este servidor) — mientras tanto usar `php artisan serve`.

## Endpoints actuales

```
POST /api/auth/registro   — crea un usuario y devuelve token
POST /api/auth/login      — valida credenciales y devuelve token
POST /api/auth/logout     — revoca el token actual        (requiere Bearer token)
GET  /api/auth/me         — datos del usuario autenticado  (requiere Bearer token)
```

Cada intento de login/registro queda auditado en la tabla `login` (ip, dispositivo, éxito/fallo y el id del token emitido en `personal_access_tokens`).

## Base de datos

El esquema completo vive en `database/sql/playboss-schema.sql`. Las migraciones en `database/migrations/` son la forma "Laravel" de aplicar ese esquema (y las complementarias que vengan después) de manera versionada — corren seguro tanto en una base nueva (`migrate:fresh`) como en una que ya tiene el esquema cargado.

### Datos de ejemplo (`database/sql/seeds/`)

Cada archivo tiene su propia migración (`seed_XX_...`), usan `INSERT IGNORE` (re-ejecutar `migrate` es seguro) y se corren con `php artisan migrate` normal:

| Archivo | Contenido | Origen del dato |
|---|---|---|
| `01_catalogos_generales.sql` | países, posiciones, estados, métodos de pago | catálogo genérico |
| `02_empresa.sql` | 1 fila: PlayBoss S.A.S. | **placeholder** (NIT/licencia no son reales) |
| `03_geografia_liga_equipos.sql` | ciudades, estadios, liga colombiana, temporada 2026-I, 20 equipos | **real** (Categoría Primera A / Liga BetPlay Dimayor, Torneo Apertura 2026 — Wikipedia) |
| `04_jugadores.sql` | ~16 jugadores por equipo | **sintético** — no se consiguió el roster real de cada club, ver comentario en el archivo |
| `05_calendario.sql` | 19 jornadas; partidos de la jornada 1 y 2 | jornada 1 **real** (resultados confirmados); jornada 2 generada por round-robin (programada, sin resultado); jornadas 3-19 solo con fechas |
| `06_cuotas.sql` | cuotas 1X2 de los partidos de jornada 2 | demo/desarrollo |
| `07_historico_alineaciones.sql` | vínculo jugador-equipo y alineación titular (4-4-2) de jornada 1 | sintético, ligado a `04_jugadores.sql` |
| `09_usuarios_demo.sql` | 5 usuarios (1 admin) + su billetera | **solo dev/QA** — contraseñas de prueba `Demo12345!` / `Admin12345!` |

## Pendiente (próximas entregas)

Apuestas y transacciones de ejemplo, rosters reales (reemplazar `04_jugadores.sql`), resto del calendario (jornadas 3-19), cuenta/billetera real, recuperación de contraseña.
