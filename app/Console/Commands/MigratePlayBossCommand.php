<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigratePlayBossCommand extends Command
{
    protected $signature = 'playboss:migrate
        {connection? : sqlsrv, mysql, pgsql, mongodb; omite para ejecutar todos}
        {--force : Ejecutar en producción sin confirmación interactiva}';

    protected $description = 'Ejecuta las migraciones por identidad en cada conexión propietaria';

    private const CONNECTIONS = ['sqlsrv', 'mysql', 'pgsql', 'mongodb'];

    public function handle(): int
    {
        $requested = $this->argument('connection');
        $connections = $requested ? [$requested] : self::CONNECTIONS;

        foreach ($connections as $connection) {
            if (!in_array($connection, self::CONNECTIONS, true)) {
                $this->components->error("Conexión no admitida: {$connection}.");
                $this->line('Usa: sqlsrv, mysql, pgsql o mongodb.');
                return self::INVALID;
            }
        }

        $checkOptions = $requested ? ['connection' => $requested] : [];
        $checkCode = Artisan::call('playboss:db-check', $checkOptions, $this->output);
        if ($checkCode !== self::SUCCESS) {
            $this->components->error('No se aplicaron migraciones porque una o más conexiones no están disponibles.');
            return $checkCode;
        }

        foreach ($connections as $connection) {
            $this->components->info("Migrando {$connection}");
            $options = [
                '--database' => $connection,
                '--path' => "database/migrations/{$connection}",
            ];
            if ($this->option('force')) {
                $options['--force'] = true;
            }

            $exitCode = Artisan::call('migrate', $options, $this->output);
            if ($exitCode !== self::SUCCESS) {
                $this->components->error("Migración interrumpida en {$connection}; revisa el error y reintenta esa conexión.");
                return $exitCode;
            }
        }

        $this->components->info('Migraciones PlayBoss finalizadas.');
        return self::SUCCESS;
    }
}
