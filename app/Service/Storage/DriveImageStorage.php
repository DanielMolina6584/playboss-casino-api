<?php

namespace App\Service\Storage;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DriveImageStorage
{
    public function __construct(private readonly Drive $drive)
    {
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

        $file = $this->drive->files->create($metadata, $options);

        return [
            'id' => $file->id,
            'name' => $file->name,
            'mime_type' => $file->mimeType,
            'web_view_link' => $file->webViewLink,
        ];
    }

    public function eliminar(string $fileId): void
    {
        $options = config('services.google_drive.shared_drive_id') ? ['supportsAllDrives' => true] : [];
        $this->drive->files->delete($fileId, $options);
    }
}
