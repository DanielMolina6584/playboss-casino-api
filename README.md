# PlayBoss API

API Laravel 12 para PlayBoss. El esquema se reparte por propiedad de datos entre SQL Server, MySQL, PostgreSQL y MongoDB; Firebase Functions coordina el proveedor de pagos y Google Drive conserva imágenes.

## Requisitos

- PHP 8.2+, Composer
- PHP drivers `pdo_mysql`, `pdo_sqlsrv`, `pdo_pgsql` y `mongodb`
- MySQL/MariaDB, SQL Server, PostgreSQL y MongoDB accesibles según `.env`
- Node.js/npm para compilar recursos Laravel si se usan

## Puesta en marcha local

```bash
composer install
cp .env.example .env
# Completa APP_KEY y los endpoints/usuarios/secretos en .env
php artisan key:generate
php artisan playboss:db-check
php artisan playboss:migrate
```

Para ejecutar una sola conexión se puede indicar `mysql`, `sqlsrv`, `pgsql` o `mongodb` en los dos comandos. La configuración MySQL local usa la base `dev_playboss`; las otras bases deben existir y tener sus credenciales cargadas. No correr las migraciones en una base compartida con otra aplicación.

El comando `composer setup` instala dependencias y usa `playboss:migrate`; en una configuración incompleta se detendrá en la primera conexión que no pueda migrar. Las migraciones no son atómicas entre motores.

## Entidades por conexión

- SQL Server: identidad, roles, credenciales, perfil, recuperación y Sanctum.
- MySQL: catálogos deportivos, ligas, equipos, jugadores, partidos y alineaciones.
- PostgreSQL: cuentas, apuestas, detalles/cuotas congeladas, movimientos y transacciones.
- MongoDB: reglas, mercados y logs de autenticación.
- Firebase Cloud Function: fachada autenticada para un proveedor de pagos elegido.
- Google Drive: archivos de imagen; los modelos guardan el ID del archivo.

Los modelos viven en `app/Models/<Dominio>` y asignan su conexión explícitamente. Los servicios de dominio están en `app/Service`. Las migraciones activas están en `database/migrations/{sqlsrv,mysql,pgsql,mongodb}`. Las migraciones anteriores de una sola base se archivaron en `database/migrations/legacy` y no se cargan al ejecutar el nuevo comando.

Lee [DATABASE-ARCHITECTURE.md](DATABASE-ARCHITECTURE.md) para el contrato de Cloud Function, variables requeridas y configuración Docker. Los secretos y JSON privados deben quedarse en `.env` local o en el gestor de secretos del despliegue; nunca en Git ni en el frontend.

## API REST

Prefijo `/api`. Todas las respuestas usan el sobre `{ "error": 0|1, "mensaje": [], "data": {...} }`; los errores de validación (422) traen además `data.errores` por campo. Autenticación: `Authorization: Bearer <token>` (Sanctum). Las listas paginadas devuelven `data.items` y `data.meta {page, per_page, total, last_page}` (`?page=&per_page=` máx. 100).

| Grupo | Endpoint | Notas |
| --- | --- | --- |
| Auth | `POST /auth/registro`, `POST /auth/login` (5/min por correo+IP y 30/min por IP), `POST /auth/logout`, `GET /auth/me` | |
| Google | `POST /auth/google` `{credential}` (id_token de Google; valida firma/aud/iss/exp y `email_verified`; crea o vincula el usuario sin contraseña; devuelve `estado: perfil_incompleto` si faltan fecha de nacimiento/documento), `PUT /auth/google/completar` | Hasta completar el perfil, `POST /apuestas` y `POST /pagos/depositos` responden 403 (`data.perfil_incompleto`). Variable `GOOGLE_CLIENT_ID`. Guía: `docs/credenciales/GOOGLE-RECAPTCHA.md` |
| 2FA (TOTP) | `GET /auth/mfa`, `POST /auth/mfa/configurar`, `/activar {codigo}`, `/desactivar {contrasena\|credential, codigo\|codigo_recuperacion}`, `/recovery-codes {codigo}`, `POST /auth/mfa/verificar {mfa_token, codigo\|codigo_recuperacion}` | Con 2FA activo, `/auth/login` y `/auth/google` devuelven `{mfa_required, mfa_token}` (sin token de API) y el token real sale de `/auth/mfa/verificar`. Ver sección *Autenticación en dos pasos*. Admin: `POST /admin/usuarios/{id}/mfa/restablecer {motivo}` |
| reCAPTCHA v3 | `recaptcha_token` (body) o header `X-Recaptcha-Token` en `/auth/registro`, `/login`, `/recuperar`, `/restablecer` | Solo si `RECAPTCHA_ENABLED=true` (`RECAPTCHA_SECRET`, `RECAPTCHA_MIN_SCORE`, acción esperada por endpoint); 422/403 si es inválido, 502 si Google no responde |
| Recuperación | `POST /auth/recuperar` `{correo}`, `POST /auth/restablecer` `{correo, codigo(6 dígitos), contrasena, contrasena_confirmation}` | Código de 15 min, un solo uso, enviado por el mailer configurado (`MAIL_MAILER=log` en local) |
| Catálogo (público) | `GET /deportes /ligas /temporadas /equipos /estadios /jugadores /jornadas` | Filtros: `q, liga_id, temporada_id, equipo_id, ciudad_id, servicio_codigo, pais_codigo` |
| | `GET /partidos` | Filtros: `liga_id, temporada_id, jornada_id, equipo_id, estado, desde, hasta, orden` |
| | `GET /partidos/{id}`, `GET /partidos/{id}/mercados` | Mercados y cuotas activas (MongoDB) |
| Perfil | `GET /perfil`, `PUT /perfil`, `PUT /perfil/contrasena` | |
| Cuenta | `GET /cuenta`, `GET /cuenta/movimientos` | Saldo disponible/retenido; filtro `tipo, desde, hasta` |
| Apuestas | `POST /apuestas`, `GET /apuestas`, `GET /apuestas/{id}`, `POST /apuestas/{id}/cancelar` | 20/min por usuario |
| Pagos | `POST /pagos/depositos`, `GET /pagos/{referencia}`, `POST /pagos/wompi/webhook` (público) | 10/min por usuario |
| Admin (rol `ADMIN`) | `apiResource /admin/equipos`, `/admin/ligas`, `/admin/partidos`, `/admin/mercados`, `/admin/reglas` | CRUD; los mercados se suspenden en lugar de borrarse |
| | `POST /admin/partidos/{id}/liquidar` `{goles_local, goles_visitante, ganadoras?}` | Liquida mercados y apuestas, abona premios |
| | `POST /admin/apuestas/{id}/anular` | Devuelve el monto retenido |
| Retiros | `POST /pagos/retiros`, `GET /pagos/retiros` | Solicitud PENDIENTE (saldo disponible -> retenido, idempotente); cuenta destino del mismo titular. Admin: `GET /admin/retiros`, `POST /admin/retiros/{id}/aprobar` (PAGADO; **TODO** payout real) y `/rechazar {motivo}` (libera saldo) |
| Admin usuarios | `GET /admin/usuarios` (`q, estado, rol, desde, hasta`), `PUT /admin/usuarios/{id}/estado` `{estado: ACT\|BLQ\|SUS}` | Sin datos sensibles; bloquear revoca tokens |
| Juego responsable | `GET/PUT /perfil/limites` | Límites diario/semanal/mensual de depósito y apuesta (PostgreSQL `limite_juego`) y autoexclusión; los aplican `POST /apuestas` y `POST /pagos/depositos` |
| Público | `POST /contacto` (5/min; Mongo `contact_messages` + correo a `CONTACT_TO_ADDRESS`), `GET /configuracion/limites`, `GET /media/equipos/{id}/escudo`, `GET /media/ligas/{id}/logo` | `escudo_url`/`logo_url` en equipos y ligas; `GET /partidos?q=` busca por equipo o liga |

### Crear apuesta

```json
POST /api/apuestas
{ "monto": "10000", "tipo": "COMBINADA", "idempotency_key": "uuid-del-cliente",
  "selecciones": [ { "match_id": 12, "market_id": "<id mongo>", "selection_code": "1", "odd": 1.85 } ] }
```

El servidor verifica: usuario activo y mayor de edad (`BET_MIN_AGE` y `min_age` de la regla), partido existente en estado `PROG` y con fecha futura, mercado `ACTIVE`, que la opción exista y su cuota coincida con `odd` si se envía (si cambió responde 409 con `odd_actual`), regla vigente (`ReglaApuesta`: monto mínimo/máximo y pago máximo), sin selecciones duplicadas, **un solo pick por partido** (evita opciones excluyentes en combinadas), máximo `BET_MAX_SELECTIONS`, saldo suficiente y límites diarios (`BET_DAILY_STAKE_LIMIT`, `BET_DAILY_COUNT_LIMIT`). El monto se retiene en PostgreSQL; `idempotency_key` (o header `Idempotency-Key`) evita duplicar la apuesta al reintentar. Pago potencial = monto × producto de cuotas (incluye el monto apostado), calculado en servidor con enteros.

### Liquidación

`POST /admin/partidos/{id}/liquidar` marca el partido `FIN` con el marcador, determina la opción ganadora de los mercados estándar (`1X2`, `BTTS`, `OU_<n>_<d>`, p. ej. `OU_2_5`) y guarda el resultado por selección (`apuesta_detalle.resultado`). Para otros mercados (o líneas enteras) indica `ganadoras: {"<market_id o code>": "<opcion>" | "VOID"}`; los no resueltos quedan pendientes. Una apuesta pierde apenas una selección pierde; gana cuando todas ganan (selecciones anuladas cuentan con cuota 1). Cada cierre libera lo retenido y abona el premio en una transacción PostgreSQL con referencia única `liquidacion:apuesta:{id}`, por lo que repetir la llamada no paga dos veces.

### Pagos con Wompi (sandbox)

Driver por defecto recomendado: `PAYMENT_GATEWAY_DRIVER=wompi` (alternativa: `firebase_function`). Variables: `WOMPI_PUBLIC_KEY`, `WOMPI_PRIVATE_KEY`, `WOMPI_EVENTS_SECRET`, `WOMPI_INTEGRITY_SECRET`, `WOMPI_BASE_URL` (`https://sandbox.wompi.co/v1`), `WOMPI_REDIRECT_URL`. Ver [CREDENCIALES.md](CREDENCIALES.md).

1. `POST /api/pagos/depositos {monto}` crea una transacción `PENDIENTE` con referencia única `PB-<id>-<rand>` y devuelve `data.checkout` (`public_key, reference, amount_in_cents, currency, signature_integrity, redirect_url, checkout_url`) para el Widget/Web Checkout.
2. El usuario paga en Wompi. Wompi llama a `POST /api/pagos/wompi/webhook` (evento `transaction.updated`).
3. El backend valida el checksum del evento con el secreto de eventos, consulta la transacción en la API de Wompi, compara referencia, monto y moneda con la local, registra el evento (`evento_pago`, idempotente por `wompi:<id>:<estado>`) y, si está `APPROVED`, acredita el saldo y crea el movimiento en una única transacción PostgreSQL.
4. El front consulta `GET /api/pagos/{referencia}`; mientras siga `PENDIENTE` el backend sincroniza con Wompi (sirve si el webhook no llega en local).

Retiros: pendientes (requieren flujo de verificación y desembolso propio; no hay endpoint).

## Autenticación en dos pasos (2FA / TOTP)

TOTP RFC 6238 (SHA1, 6 dígitos, 30 s, tolerancia ±1 ventana), compatible con Google Authenticator, Authy, Microsoft Authenticator, 1Password, etc. Implementado sin dependencias nuevas (`app/Service/Auth/Mfa/Totp.php`) y sin servicios de terceros.

- **Migración** (SQL Server): `php artisan playboss:migrate sqlsrv` crea `usuario_mfa` y `usuario_mfa_recuperacion`. Hasta correrla, `/auth/me` y el login fallan con `MFA_ENABLED=true`.
- **Variables**: `MFA_ENABLED` (por defecto `true`; `false` apaga todo el 2FA), `MFA_ISSUER` (nombre que muestra la app, `PlayBoss`), `MFA_REQUIRED_ROLES` (por defecto `ADMIN`).
- **Almacenamiento**: el secreto se guarda cifrado con `APP_KEY` (`Crypt`); **si cambias `APP_KEY` los secretos dejan de descifrarse** y habrá que restablecer el 2FA de cada usuario. Se guarda el último contador usado (un código no se reutiliza) y 10 códigos de recuperación como HMAC-SHA256 (un solo uso).
- **Login**: con 2FA activo no se emite token Sanctum; se devuelve un `mfa_token` opaco (caché, 5 min, un solo uso, se quema tras 5 fallos) que **no pasa `auth:sanctum`**. `POST /auth/mfa/verificar` lo canjea. La caché debe ser compartida entre procesos (`CACHE_STORE=file|database|redis`, no `array`).
- **Bloqueo**: 5 fallos en 5 min por usuario+IP y 15 en 15 min por usuario (cualquier IP) → 429 con `data.retry_after`, aunque el siguiente código sea correcto.
- **Política**: los roles de `MFA_REQUIRED_ROLES` reciben 403 con `data.mfa_requerido: true` en `/admin/*` y en `POST /pagos/retiros` hasta activarlo, y no pueden desactivarlo. Cualquier usuario con 2FA activo debe enviar `codigo_mfa` (o `codigo_recuperacion`) en cada `POST /pagos/retiros`; sin 2FA el retiro sigue como antes.
- **Auditoría** (MongoDB `audit_logs`): `mfa.setup_started`, `mfa.enabled`, `mfa.disabled`, `mfa.recovery_used`, `mfa.recovery_regenerated`, `mfa.locked`, `mfa.admin_reset`, y los fallos como eventos `authentication` con `success=false`.
- **Soporte**: `POST /admin/usuarios/{id}/mfa/restablecer` (otro admin) o, si no queda ninguno, `php artisan playboss:mfa-reset correo@dominio.com`.
- Guía de uso para el dueño: `docs/credenciales/MFA.md` (carpeta ignorada por git).

## Pruebas

```bash
php vendor/bin/phpunit
```

Cubren el 2FA (`TotpTest` con los vectores del RFC 6238; `MfaTest` contra un almacén en memoria, sin SQL Server), cálculo de pago potencial, reglas de selecciones, resolución de mercados, firma de integridad y checksum de Wompi (HTTP simulado), validaciones de apuestas/depósitos, roles, CORS y webhook. No requieren las bases remotas. Las operaciones transaccionales con PostgreSQL/MongoDB (crear apuesta, liquidar, acreditar saldo) solo se pueden verificar contra bases reales tras `playboss:migrate`, porque el entorno de pruebas no tiene `pdo_sqlite`.

## Documentación Swagger

La API está documentada con OpenAPI 3.0.3 y Swagger UI (assets `swagger-ui-dist@5.17.14` desde jsDelivr; sin dependencias nuevas de Composer).

- UI: `GET /api/documentation` (botón **Authorize**: pega el token Bearer de `/auth/login`, sin el prefijo `Bearer`).
- Spec: `GET /api/openapi.json`.
- Activación: `API_DOCS_ENABLED` (ver `.env.example`). Si se omite, solo está activa con `APP_ENV=local`; con `false` ambas URLs responden 404. Funciona igual en Docker (no usa herramientas de desarrollo).
- Fuente única: `resources/openapi/openapi.php` (array PHP con helpers y esquemas reutilizables; rutas sin el prefijo `/api`).

Al agregar o cambiar un endpoint: añade o edita su entrada en `paths` (tag, resumen, parámetros, request/response, errores, `security`) y los esquemas en `components.schemas`. `OpenApiDocsTest` falla si una ruta `/api` no está documentada o si se documenta una que no existe. Para validar el spec con una herramienta externa: volcar el JSON de `/api/openapi.json` a un archivo y ejecutar `npx @redocly/cli lint archivo.json`.

## Errores, límites y CORS

Errores JSON uniformes (401, 403, 404, 409, 422, 429, 502, 500). Rate limits: login 5/min por correo+IP y 30/min por IP; registro, recuperar y restablecer comparten un bucket de 5/min por IP; catálogo público 120/min; apuestas 20/min y pagos 10/min por usuario; webhook 120/min por IP. Idempotencia: `idempotency_key` (cuerpo) tiene prioridad sobre el header `Idempotency-Key`; sin ninguna se deriva una clave determinista (usuario + payload + ventana de 30 s). `DELETE /admin/mercados/{id}` suspende (no borra) y devuelve el mercado. CORS: `CORS_ALLOWED_ORIGINS` (por defecto `http://localhost:5173`).
