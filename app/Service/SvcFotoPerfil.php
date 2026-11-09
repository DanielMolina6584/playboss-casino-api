<?php

namespace App\Service;

use App\Models\Usuario\PerfilUsuario;
use App\Service\Storage\DriveImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/** Foto de perfil del usuario: el archivo vive en Google Drive y su id en usuario_perfil (SQL Server). */
class SvcFotoPerfil
{
    /** Reglas de validación del archivo (jpg, png o webp de máximo 3 MB). */
    public const REGLAS = ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];

    public function __construct(private readonly DriveImageStorage $drive)
    {
    }

    /**
     * Sube la foto a Drive, guarda su id en el perfil y borra la foto anterior.
     * @return string|null id del archivo en Drive, o null si falló
     */
    public function guardar(int $usuarioId, UploadedFile $foto): ?string
    {
        try {
            $perfil = PerfilUsuario::findOrFail($usuarioId);
            $anterior = $perfil->foto_drive_id;
            $extension = $foto->guessExtension() ?: 'jpg';
            $archivo = $this->drive->guardar($foto, sprintf('perfil-%d-%s.%s', $usuarioId, now()->format('Ymd-His'), $extension));

            $perfil->foto_drive_id = $archivo['id'];
            $perfil->save();

            if ($anterior) {
                $this->eliminarDeDrive($anterior);
            }

            return $archivo['id'];
        } catch (\Throwable $err) {
            Log::channel('single')->error('SvcFotoPerfil::guardar - ' . $err->getMessage());
            return null;
        }
    }

    /**
     * Descarga la foto del usuario desde Drive.
     * @return array{contenido:string,mime_type:string}|null null si no tiene foto o Drive falló
     */
    public function descargar(int $usuarioId): ?array
    {
        try {
            $driveId = PerfilUsuario::whereKey($usuarioId)->value('foto_drive_id');
            return $driveId ? $this->drive->descargar($driveId) : null;
        } catch (\Throwable $err) {
            Log::channel('single')->error('SvcFotoPerfil::descargar - ' . $err->getMessage());
            return null;
        }
    }

    /** Borra un archivo de Drive sin interrumpir el flujo si falla. */
    private function eliminarDeDrive(string $driveId): void
    {
        try {
            $this->drive->eliminar($driveId);
        } catch (\Throwable $err) {
            Log::channel('single')->warning('SvcFotoPerfil::eliminarDeDrive - ' . $err->getMessage());
        }
    }
}
