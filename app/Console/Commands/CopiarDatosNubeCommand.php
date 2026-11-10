<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Copia los datos de las bases locales a las de la nube (.env.nube), conservando los ids
 * para que las referencias entre motores (usuario, partido, mercado...) sigan siendo válidas.
 * Las tablas de la nube deben existir antes: php artisan playboss:migrate --env=nube
 * Ejemplo: php artisan playboss:copiar-a-nube   (o solo un motor: php artisan playboss:copiar-a-nube pgsql)
 */
class CopiarDatosNubeCommand extends Command
{
    protected $signature = 'playboss:copiar-a-nube
        {connection? : sqlsrv, mysql, pgsql o mongodb; omite para copiar todas}
        {--forzar : Reemplaza los datos que ya existan en la nube sin preguntar}
        {--archivo=.env.nube : Archivo con las conexiones de destino}';

    protected $description = 'Copia los datos locales a las bases de datos de la nube configuradas en .env.nube';

    private const CONEXIONES = ['sqlsrv', 'mysql', 'pgsql', 'mongodb'];

    /** Tablas que no se copian: control de migraciones y sesiones locales. */
    private const OMITIR = ['migrations', 'personal_access_tokens'];

    /** SQL Server acepta máximo 2100 parámetros por consulta. */
    private const MAX_PARAMETROS = 2000;

    public function handle(): int
    {
        $archivo = base_path((string) $this->option('archivo'));
        if (!is_file($archivo)) {
            $this->components->error("No existe {$this->option('archivo')}. Cópialo desde .env.nube.example y llena los datos.");
            return self::FAILURE;
        }
        $nube = Dotenv::parse((string) file_get_contents($archivo));

        $pedida = $this->argument('connection');
        if ($pedida && !in_array($pedida, self::CONEXIONES, true)) {
            $this->components->error('Usa: sqlsrv, mysql, pgsql o mongodb.');
            return self::FAILURE;
        }

        foreach ($pedida ? [$pedida] : self::CONEXIONES as $conexion) {
            $this->components->info("Copiando {$conexion} → nube");
            try {
                $this->registrarConexionNube($conexion, $nube);
                $conexion === 'mongodb' ? $this->copiarMongo() : $this->copiarSql($conexion);
            } catch (\Throwable $e) {
                $this->components->error("{$conexion}: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        $this->components->info('Datos copiados a la nube.');
        return self::SUCCESS;
    }

    /** Crea la conexión "<motor>_nube" con los datos de .env.nube sobre la configuración local. */
    private function registrarConexionNube(string $conexion, array $env): void
    {
        $base = config("database.connections.{$conexion}");
        $valor = fn (string $clave) => $env[$clave] ?? throw new RuntimeException("Falta {$clave} en .env.nube");

        $config = match ($conexion) {
            'mongodb' => array_merge($base, ['dsn' => $valor('MONGODB_URI'), 'database' => $valor('MONGODB_DATABASE')]),
            'mysql' => array_merge($base, [
                'url' => null, 'host' => $valor('MYSQL_HOST'), 'port' => $valor('MYSQL_PORT'),
                'database' => $valor('MYSQL_DATABASE'), 'username' => $valor('MYSQL_USERNAME'), 'password' => $valor('MYSQL_PASSWORD'),
                'options' => array_filter([\PDO::MYSQL_ATTR_SSL_CA => $this->ruta($env['MYSQL_ATTR_SSL_CA'] ?? null)]),
            ]),
            'pgsql' => array_merge($base, [
                'url' => null, 'host' => $valor('POSTGRES_HOST'), 'port' => $valor('POSTGRES_PORT'),
                'database' => $valor('POSTGRES_DATABASE'), 'username' => $valor('POSTGRES_USERNAME'), 'password' => $valor('POSTGRES_PASSWORD'),
                'sslmode' => $env['POSTGRES_SSLMODE'] ?? 'require',
            ]),
            'sqlsrv' => array_merge($base, [
                'url' => null, 'host' => $valor('SQLSERVER_HOST'), 'port' => $valor('SQLSERVER_PORT'),
                'database' => $valor('SQLSERVER_DATABASE'), 'username' => $valor('SQLSERVER_USERNAME'), 'password' => $valor('SQLSERVER_PASSWORD'),
                'encrypt' => $env['SQLSERVER_ENCRYPT'] ?? 'yes',
                'trust_server_certificate' => $env['SQLSERVER_TRUST_SERVER_CERTIFICATE'] ?? 'false',
            ]),
        };

        config(["database.connections.{$conexion}_nube" => $config]);
        DB::purge("{$conexion}_nube");
    }

    private function ruta(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }
        return str_starts_with($ruta, '/') ? $ruta : base_path($ruta);
    }

    /** Copia todas las tablas de un motor SQL en orden de llaves foráneas. */
    private function copiarSql(string $conexion): void
    {
        $origen = DB::connection($conexion);
        $destino = DB::connection("{$conexion}_nube");
        $tablas = $this->ordenarPorDependencias($conexion);

        $faltantes = array_diff($tablas, $this->tablas("{$conexion}_nube"));
        if ($faltantes) {
            throw new RuntimeException('En la nube faltan tablas (' . implode(', ', $faltantes) . '). Corre primero: php artisan playboss:migrate --env=nube');
        }

        // Las migraciones dejan catálogos cargados (roles, estados...): se reemplazan por los locales.
        $conDatos = array_filter($tablas, fn ($t) => $destino->table($t)->exists());
        $this->confirmarReemplazo($conexion, $conDatos);
        foreach (array_reverse($tablas) as $tabla) {
            $destino->table($tabla)->delete();
        }

        if ($conexion === 'mysql') {
            $destino->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ($tablas as $tabla) {
            $total = $this->copiarTabla($origen, $destino, $conexion, $tabla);
            $this->components->twoColumnDetail($tabla, number_format($total) . ' filas');
        }

        if ($conexion === 'mysql') {
            $destino->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        if ($conexion === 'pgsql') {
            $this->ajustarSecuencias($destino, $tablas);
        }
    }

    private function copiarTabla(Connection $origen, Connection $destino, string $conexion, string $tabla): int
    {
        $columnas = Schema::connection($conexion)->getColumns($tabla);
        $porLote = max(1, min(500, intdiv(self::MAX_PARAMETROS, count($columnas))));
        $identidad = $conexion === 'sqlsrv' && collect($columnas)->contains('auto_increment', true);
        $orden = $this->llavePrimaria($conexion, $tabla) ?: [$columnas[0]['name']];

        $consulta = $origen->table($tabla);
        foreach ($orden as $columna) {
            $consulta->orderBy($columna);
        }

        $total = 0;
        $insertar = function ($filas) use ($destino, $tabla, &$total) {
            $destino->table($tabla)->insert(array_map(fn ($f) => (array) $f, $filas->all()));
            $total += $filas->count();
        };

        if ($identidad) {
            // IDENTITY_INSERT solo vale dentro de la misma sesión: se envuelve en una transacción.
            $destino->transaction(function () use ($destino, $consulta, $tabla, $porLote, $insertar) {
                $destino->unprepared("SET IDENTITY_INSERT [{$tabla}] ON");
                $consulta->chunk($porLote, $insertar);
                $destino->unprepared("SET IDENTITY_INSERT [{$tabla}] OFF");
            });
        } else {
            $consulta->chunk($porLote, $insertar);
        }

        return $total;
    }

    /** @return string[] columnas de la llave primaria */
    private function llavePrimaria(string $conexion, string $tabla): array
    {
        foreach (Schema::connection($conexion)->getIndexes($tabla) as $indice) {
            if ($indice['primary']) {
                return $indice['columns'];
            }
        }
        return [];
    }

    /** Ordena las tablas para insertar primero las referenciadas por llaves foráneas. */
    private function ordenarPorDependencias(string $conexion): array
    {
        $esquema = Schema::connection($conexion);
        $tablas = array_values(array_diff($this->tablas($conexion), self::OMITIR));
        $depende = [];
        foreach ($tablas as $tabla) {
            $depende[$tabla] = array_values(array_intersect(
                array_unique(array_column($esquema->getForeignKeys($tabla), 'foreign_table')),
                $tablas
            ));
        }

        $orden = [];
        while (count($orden) < count($tablas)) {
            $avance = false;
            foreach ($tablas as $tabla) {
                if (!in_array($tabla, $orden, true) && !array_diff(array_diff($depende[$tabla], [$tabla]), $orden)) {
                    $orden[] = $tabla;
                    $avance = true;
                }
            }
            if (!$avance) {
                throw new RuntimeException('Dependencias circulares entre tablas.');
            }
        }

        return $orden;
    }

    /** Pide confirmación antes de borrar datos de la nube (se omite con --forzar). */
    private function confirmarReemplazo(string $conexion, array $tablas): void
    {
        if (!$tablas || $this->option('forzar')) {
            return;
        }
        $lista = implode(', ', $tablas);
        if (!$this->confirm("{$conexion}: la nube ya tiene datos en {$lista}. ¿Reemplazarlos por los locales?", true)) {
            throw new RuntimeException('Copia cancelada.');
        }
    }

    /** Tablas del esquema de la aplicación (dbo, public o la base MySQL), sin las del sistema. */
    private function tablas(string $conexion): array
    {
        $db = DB::connection($conexion);
        $esquema = match ($db->getDriverName()) {
            'sqlsrv' => 'dbo',
            'pgsql' => 'public',
            default => $db->getDatabaseName(),
        };

        return array_column(Schema::connection($conexion)->getTables($esquema), 'name');
    }

    /** Deja las secuencias de PostgreSQL después del id más alto copiado. */
    private function ajustarSecuencias(Connection $destino, array $tablas): void
    {
        foreach ($tablas as $tabla) {
            foreach (Schema::connection('pgsql_nube')->getColumns($tabla) as $columna) {
                if (!str_contains((string) $columna['default'], 'nextval(')) {
                    continue;
                }
                $c = $columna['name'];
                $destino->select("SELECT setval(pg_get_serial_sequence(?, ?), COALESCE(MAX(\"{$c}\"), 1), MAX(\"{$c}\") IS NOT NULL) FROM \"{$tabla}\"", [$tabla, $c]);
            }
        }
    }

    /** Copia las colecciones de MongoDB conservando los _id. */
    private function copiarMongo(): void
    {
        $origen = DB::connection('mongodb')->getDatabase();
        $destino = DB::connection('mongodb_nube')->getDatabase();

        foreach ($origen->listCollectionNames() as $nombre) {
            if (in_array($nombre, self::OMITIR, true)) {
                continue;
            }
            $coleccion = $destino->selectCollection($nombre);
            if ($coleccion->countDocuments() > 0) {
                $this->confirmarReemplazo('mongodb', [$nombre]);
                $coleccion->deleteMany([]);
            }

            $lote = [];
            $total = 0;
            foreach ($origen->selectCollection($nombre)->find() as $doc) {
                $lote[] = $doc;
                if (count($lote) === 1000) {
                    $coleccion->insertMany($lote);
                    $total += 1000;
                    $lote = [];
                }
            }
            if ($lote) {
                $coleccion->insertMany($lote);
                $total += count($lote);
            }
            $this->components->twoColumnDetail($nombre, number_format($total) . ' documentos');
        }
    }
}
