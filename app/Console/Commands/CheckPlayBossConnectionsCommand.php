<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckPlayBossConnectionsCommand extends Command
{
    protected $signature = 'playboss:db-check {connection? : sqlsrv, mysql, pgsql, mongodb; omite para revisar todos}';
    protected $description = 'Prueba las conexiones configuradas con consultas de solo lectura y clasifica el error (sin mostrar secretos)';

    private const CONNECTIONS = ['sqlsrv', 'mysql', 'pgsql', 'mongodb'];

    private const EXTENSIONS = [
        'sqlsrv' => 'pdo_sqlsrv',
        'mysql' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'mongodb' => 'mongodb',
    ];

    public function handle(): int
    {
        $requested = $this->argument('connection');
        $connections = $requested ? [$requested] : self::CONNECTIONS;
        $failed = false;

        foreach ($connections as $name) {
            if (!in_array($name, self::CONNECTIONS, true)) {
                $this->components->error("Conexión no admitida: {$name}.");
                return self::INVALID;
            }

            $target = $this->maskedTarget($name);

            if (!extension_loaded(self::EXTENSIONS[$name])) {
                $failed = true;
                $this->components->twoColumnDetail("{$name} [{$target}]", '<fg=red>sin conexión</> [DRIVER] falta la extensión ' . self::EXTENSIONS[$name]);
                continue;
            }

            $started = microtime(true);

            try {
                if ($name === 'mongodb') {
                    $info = DB::connection('mongodb')->getDatabase()->command(['buildInfo' => 1])->toArray()[0] ?? null;
                    $version = is_object($info) ? ($info->version ?? '?') : '?';
                } else {
                    $row = DB::connection($name)->selectOne($this->versionQuery($name));
                    $version = $this->shortVersion((string) (array_values((array) $row)[0] ?? '?'));
                }
                $ms = (int) round((microtime(true) - $started) * 1000);
                $this->components->twoColumnDetail(
                    "{$name} [{$target}]",
                    "<fg=green>conectada</> v{$version} {$ms} ms"
                );
            } catch (Throwable $exception) {
                $failed = true;
                // Solo se muestra la categoría; nunca el mensaje del driver (puede incluir host, usuario o DSN).
                $this->components->twoColumnDetail(
                    "{$name} [{$target}]",
                    '<fg=red>sin conexión</> [' . $this->classify($exception) . '] ' . class_basename($exception)
                );
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function versionQuery(string $name): string
    {
        return match ($name) {
            'sqlsrv' => 'SELECT CAST(SERVERPROPERTY(\'ProductVersion\') AS varchar(50)) AS v',
            'pgsql' => 'SHOW server_version',
            default => 'SELECT VERSION() AS v',
        };
    }

    private function shortVersion(string $version): string
    {
        return preg_match('/\d+(\.\d+)+/', $version, $m) ? $m[0] : substr($version, 0, 20);
    }

    private function classify(Throwable $e): string
    {
        $m = strtolower($e->getMessage());
        $has = fn (array $needles) => (bool) array_filter($needles, fn ($n) => str_contains($m, $n));

        return match (true) {
            $has(['could not find driver', 'unknown driver', 'driver not found']) => 'DRIVER',
            $has(['ssl', 'tls', 'certificate', 'handshake']) => 'SSL/TLS',
            $has(['password authentication', 'authentication failed', 'access denied', 'login failed', 'sasl', 'bad auth', 'unauthorized', 'not authorized']) => 'AUTH (usuario/clave)',
            $has(['getaddrinfo', 'name or service not known', 'nodename nor servname', 'no such host', 'could not translate host', 'dns', 'resolve', 'php_network_getaddresses']) => 'DNS/host',
            $has(['timed out', 'timeout', 'connection refused', 'no route', 'unreachable', 'pg_hba', 'not allowed to connect', 'ip address', 'firewall', 'server selection', 'could not connect', 'tcp provider', 'network-related']) => 'FIREWALL/RED',
            $has(['unknown database', 'does not exist', 'cannot open database']) => 'BASE inexistente',
            default => 'OTRO',
        };
    }

    private function maskedTarget(string $name): string
    {
        $config = config("database.connections.{$name}");
        $host = null;

        $url = $config['url'] ?? $config['dsn'] ?? null;
        if ($url) {
            $normalized = $name === 'mongodb' ? preg_replace('/^mongodb(\+srv)?:/', 'http:', (string) $url) : (string) $url;
            $host = parse_url($normalized, PHP_URL_HOST) ?: null;
        }
        $host ??= $config['host'] ?? null;

        if (!$host) {
            return 'sin host';
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $host;
        }

        $parts = explode('.', $host);
        $first = $parts[0];
        $parts[0] = substr($first, 0, 2) . str_repeat('*', max(3, min(6, strlen($first) - 2)));

        return implode('.', $parts);
    }
}
