# Migraciones históricas de una sola base

Estos archivos describen la versión anterior de PlayBoss, donde las 33 tablas
se creaban en una única conexión. Se conservan como referencia y para planear
una migración de datos; el comando `php artisan playboss:migrate` no los ejecuta.

Las migraciones activas viven en `sqlsrv/`, `mysql/`, `pgsql/` y `mongodb/` y se
ejecutan por conexión. No copies tablas entre motores hasta definir una
migración de datos con respaldo y validación de IDs, saldos y apuestas.
