# Conexiones de PlayBoss API

Cómo se conecta la API a cada almacenamiento y cómo mostrar cada uno funcionando.
Para instalar el entorno ver [SETUP-LOCAL.md](SETUP-LOCAL.md).

## Arquitectura: cada dato vive en el motor que le corresponde

```
                         ┌────────────────────────┐
                         │   PlayBoss API Laravel │
                         └───────────┬────────────┘
   ┌──────────────┬──────────────┬───┴──────────┬──────────────┬──────────────┐
   │ SQL Server   │ MySQL        │ PostgreSQL   │ MongoDB      │ Firebase +   │
   │ identidad    │ catálogo     │ dinero       │ mercados     │ Google Drive │
   │ usuarios     │ deportivo    │ cuentas      │ cuotas       │ nube         │
   │ tokens       │ partidos     │ apuestas     │ reglas       │ (Firestore,  │
   │              │              │ pagos        │ auditoría    │  archivos)   │
   └──────────────┴──────────────┴──────────────┴──────────────┴──────────────┘
```

| Motor | Por qué ahí | Qué guarda |
|-------|-------------|------------|
| **SQL Server** | Identidad con integridad referencial fuerte | `usuario`, `usuario_credencial`, `usuario_perfil`, `rol`, `estado_usuario`, `tipo_documento`, `codigo_recuperacion`, `personal_access_tokens` |
| **MySQL** | Catálogo relacional de mucha lectura | `pais`, `ciudad`, `estadio`, `equipo`, `liga`, `temporada`, `jornada`, `partido`, `jugador`, `alineacion`… |
| **PostgreSQL** | Transacciones ACID y bloqueos para el saldo | `cuenta`, `movimiento`, `apuesta`, `apuesta_detalle`, `transaccion`, `evento_pago`, `metodo_pago` |
| **MongoDB** | Documentos flexibles que cambian seguido | colecciones `markets` (cuotas), `bet_rules`, `audit_logs` |
| **Firebase Firestore** | Base NoSQL en la nube de Google | colecciones del proyecto `playboss-7d872` |
| **Google Drive** | Almacenamiento de archivos | imágenes (escudos, fotos); los modelos guardan solo el ID del archivo |

Entre motores no hay llaves foráneas: se relacionan por ID (por ejemplo `apuesta_detalle.partido_id_mysql` apunta a `partido.id_partido` de MySQL y `mercado_id_mongodb` a un documento de `markets`).

## Cómo está hecha cada conexión (las 4 capas)

### 1. Variables de entorno — `.env`

Cada motor tiene sus propias variables, para no mezclar credenciales:

```env
MYSQL_HOST=127.0.0.1        MYSQL_PORT=3306    MYSQL_DATABASE=dev_playboss
SQLSERVER_HOST=127.0.0.1    SQLSERVER_PORT=1433 SQLSERVER_DATABASE=playboss
POSTGRES_HOST=127.0.0.1     POSTGRES_PORT=5432  POSTGRES_DATABASE=playboss
MONGODB_URI="mongodb://playboss:...@127.0.0.1:27017/?authSource=admin"
FIREBASE_PROJECT_ID=playboss-7d872
FIREBASE_CREDENTIALS=storage/app/private/firebase/service-account.json
GOOGLE_DRIVE_CREDENTIALS=storage/app/private/google-drive/service-account.json
GOOGLE_DRIVE_FOLDER_ID=...
```

### 2. Configuración — `config/database.php` y `config/services.php`

`config/database.php` declara una conexión por motor (`mysql`, `sqlsrv`, `pgsql`, `mongodb`), cada una con su driver PDO (o el driver oficial de MongoDB) y leyendo sus variables `MYSQL_*`, `SQLSERVER_*`, `POSTGRES_*`, `MONGODB_*`.

`config/services.php` tiene los bloques `firebase` y `google_drive` (ruta del JSON, proyecto, carpeta).

### 3. Modelos base — `app/Models`

Cada modelo hereda de una clase base que fija su conexión, así ningún modelo puede escribir en el motor equivocado:

| Clase base | `$connection` | Modelos |
|------------|---------------|---------|
| `SqlServerModel`, `SqlServerAuthenticatable` | `sqlsrv` | `app/Models/Usuario/*` |
| `MysqlModel` | `mysql` | `app/Models/Sports/*` |
| `PostgresModel` | `pgsql` | `app/Models/Account/*`, `Bet/*`, `Payment/*` |
| `MongoDocument` | `mongodb` | `app/Models/Market/*` |

Firestore y Drive no son Eloquent: se registran como servicios en `app/Providers/AppServiceProvider.php` (cliente `Google\Client`; Firestore se autentica con la cuenta de servicio y Drive con OAuth de la cuenta dueña de la carpeta) y se inyectan donde se usan (por ejemplo `app/Service/Storage/DriveImageStorage.php`).

### 4. Migraciones por motor — `database/migrations/{sqlsrv,mysql,pgsql,mongodb}`

`php artisan playboss:migrate` primero verifica las conexiones y luego ejecuta `migrate --database=<motor> --path=database/migrations/<motor>` en cada una. Cada motor lleva su propia tabla `migrations`.

## Demostración paso a paso

### Paso 1 — Los motores corriendo

```bash
docker compose ps            # postgres, sqlserver, mongodb: healthy
systemctl status mariadb     # MySQL/MariaDB local (o el contenedor playboss-mysql)
```

### Paso 2 — Laravel conectado a todo

```bash
php artisan playboss:db-check
php artisan playboss:cloud-check
```

### Paso 3 — Las tablas de cada motor

```bash
php artisan db:show --database=sqlsrv
php artisan db:show --database=mysql
php artisan db:show --database=pgsql
php artisan db:table usuario --database=sqlsrv    # detalle de una tabla
```

### Paso 4 — Un flujo que toca varios motores

1. `POST /api/auth/registro` → crea el usuario en **SQL Server** y deja un registro en `audit_logs` de **MongoDB**.
2. `GET /api/partidos` → lee de **MySQL**.
3. `GET /api/partidos/{id}/mercados` → lee cuotas de **MongoDB**.
4. `POST /api/apuestas` → valida en MySQL + MongoDB y guarda apuesta y movimiento de saldo en **PostgreSQL**.

Todo se puede probar desde Swagger: http://localhost:8000/api/documentation

### Paso 5 — Ver los datos dentro de cada motor

Consola:

```bash
# SQL Server
docker exec -it playboss-sqlserver /opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa -P 'PlayBoss_Local2026' -d playboss -Q "SELECT id_usuario, rol_codigo, estado_codigo FROM usuario"

# MySQL / MariaDB
mysql -h 127.0.0.1 -u playboss -pPlayBoss_Local2026 dev_playboss -e "SHOW TABLES"

# PostgreSQL
docker exec -it playboss-postgres psql -U playboss -d playboss -c "\dt"

# MongoDB
docker exec -it playboss-mongodb mongosh -u playboss -p PlayBoss_Local2026 --authenticationDatabase admin playboss --eval "show collections"
```

Gráfico: **DBeaver** (gratis) se conecta a los 4 motores desde Windows con `localhost` y los datos de la tabla de [SETUP-LOCAL.md](SETUP-LOCAL.md) (en SQL Server marcar *Trust server certificate*; en MongoDB usar la URI). Firestore se muestra en la consola de Firebase y Drive en la carpeta compartida.
