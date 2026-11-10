<?php

namespace App\Service\Storage;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DriveImageStorage
{
    private ?Drive $drive = null;

    /**
     * El cliente de Drive se crea solo cuando se usa: así login y registro no dependen
     * de que las credenciales de Google se puedan leer en ese momento.
     */
    public function __construct(private readonly Container $container)
    {
    }

    private function drive(): Drive
    {
        return $this->drive ??= $this->container->make(Drive::class);
    }

    /** @return array{id:string,name:string,mime_type:string,web_view_link:?string} */
    public function guardar(UploadedFile $image, ?string $name = null): array
    {
        $folderId = config('services.google_drive.folder_id');
        if (!is_string($folderId) || $folderId === '') {
            throw new RuntimeException('Configura GOOGLE_DRIVE_FOLDER_ID antes de guardar imágenes.');
        }

        $metadata = new DriveFile([
            'name' => $name ?: $image->getClientOriginalName(),
            'parents' => [$folderId],
        ]);
        $options = [
            'data' => file_get_contents($image->getRealPath()),
            'mimeType' => $image->getMimeType(),
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,webViewLink',
        ];
        if (config('services.google_drive.shared_drive_id')) {
            $options['supportsAllDrives'] = true;
        }

        $file = $this->drive()->files->create($metadata, $options);

        return [
            'id' => $file->id,
            'name' => $file->name,
            'mime_type' => $file->mimeType,
            'web_view_link' => $file->webViewLink,
        ];
    }

    /**
     * Descarga el contenido de un archivo de Drive.
     * @return array{contenido:string,mime_type:string}
     */
    public function descargar(string $fileId): array
    {
        $options = config('services.google_drive.shared_drive_id') ? ['supportsAllDrives' => true] : [];
        $meta = $this->drive()->files->get($fileId, $options + ['fields' => 'mimeType']);
        $respuesta = $this->drive()->files->get($fileId, $options + ['alt' => 'media']);

        return [
            'contenido' => (string) $respuesta->getBody(),
            'mime_type' => $meta->mimeType ?: 'application/octet-stream',
        ];
    }

    public function eliminar(string $fileId): void
    {
        $options = config('services.google_drive.shared_drive_id') ? ['supportsAllDrives' => true] : [];
        $this->drive()->files->delete($fileId, $options);
    }
}
