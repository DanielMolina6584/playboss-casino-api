<?php

namespace App\Console\Commands;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Firestore;
use Google\Service\Firestore\ListCollectionIdsRequest;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class CheckPlayBossCloudCommand extends Command
{
    protected $signature = 'playboss:cloud-check {servicio? : firestore, drive; omite para revisar ambos}';
    protected $description = 'Prueba las conexiones con Firebase Firestore y Google Drive';

    private const SERVICIOS = ['firestore', 'drive'];

    public function handle(): int
    {
        $requested = $this->argument('servicio');
        $servicios = $requested ? [$requested] : self::SERVICIOS;
        $failed = false;

        foreach ($servicios as $servicio) {
            if (!in_array($servicio, self::SERVICIOS, true)) {
                $this->components->error("Servicio no admitido: {$servicio}.");
                return self::INVALID;
            }

            try {
                $detalle = $servicio === 'firestore' ? $this->probarFirestore() : $this->probarDrive();
                $this->components->twoColumnDetail($servicio, "<fg=green>conectada</> ({$detalle})");
            } catch (Throwable $exception) {
                $failed = true;
                $this->components->twoColumnDetail($servicio, '<fg=red>sin conexión</>');
                $this->line('  ' . $this->mensajeError($exception));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Lista las colecciones raíz de Firestore (solo lectura).
     */
    private function probarFirestore(): string
    {
        $firestore = app(Firestore::class);
        $parent = sprintf(
            'projects/%s/databases/%s/documents',
            config('services.firebase.project_id'),
            config('services.firebase.firestore_database')
        );

        $respuesta = $firestore->projects_databases_documents->listCollectionIds(
            $parent,
            new ListCollectionIdsRequest(['pageSize' => 20])
        );
        $colecciones = $respuesta->getCollectionIds() ?? [];

        return 'proyecto ' . config('services.firebase.project_id') . ', colecciones: '
            . ($colecciones === [] ? 'ninguna' : implode(', ', $colecciones));
    }

    /**
     * Lee la carpeta destino y sube/borra un archivo de prueba para validar permisos y cuota.
     */
    private function probarDrive(): string
    {
        $folderId = config('services.google_drive.folder_id');
        if (!is_string($folderId) || $folderId === '') {
            throw new RuntimeException('GOOGLE_DRIVE_FOLDER_ID no está configurado.');
        }

        $drive = app(Drive::class);
        $carpeta = $drive->files->get($folderId, ['fields' => 'id,name', 'supportsAllDrives' => true]);

        $archivo = $drive->files->create(
            new DriveFile(['name' => 'playboss-cloud-check.txt', 'parents' => [$folderId]]),
            [
                'data' => 'PlayBoss cloud-check ' . now()->toIso8601String(),
                'mimeType' => 'text/plain',
                'uploadType' => 'multipart',
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]
        );
        $drive->files->delete($archivo->id, ['supportsAllDrives' => true]);

        return "carpeta \"{$carpeta->name}\", subida y borrado de prueba OK";
    }

    /**
     * Devuelve el mensaje legible del error, sin rutas ni credenciales.
     */
    private function mensajeError(Throwable $exception): string
    {
        if ($exception instanceof GoogleServiceException) {
            $errores = $exception->getErrors();
            $mensaje = is_array($errores) && isset($errores[0]['message'])
                ? $errores[0]['message']
                : 'Google respondió HTTP ' . $exception->getCode();

            return "HTTP {$exception->getCode()}: {$mensaje}";
        }

        return $exception::class . ': ' . $exception->getMessage();
    }
}
