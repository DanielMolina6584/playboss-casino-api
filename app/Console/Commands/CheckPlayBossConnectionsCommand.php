<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckPlayBossConnectionsCommand extends Command
{
    protected $signature = 'playboss:db-check {connection? : sqlsrv, mysql, pgsql, mongodb; omite para revisar todos}';
    protected $description = 'Prueba las conexiones configuradas con consultas de solo lectura';

    private const CONNECTIONS = ['sqlsrv', 'mysql', 'pgsql', 'mongodb'];

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

            try {
                if ($name === 'mongodb') {
                    DB::connection('mongodb')->getDatabase()->command(['ping' => 1]);
                } else {
                    DB::connection($name)->select('SELECT 1 AS connection_ok');
                }
                $this->components->twoColumnDetail($name, '<fg=green>conectada</>');
            } catch (Throwable $exception) {
                $failed = true;
                // No mostrar DSN, usuario ni mensaje del driver para evitar filtrar configuración.
                $this->components->twoColumnDetail($name, '<fg=red>sin conexión</> (' . $exception::class . ')');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
