<?php

namespace App\Service\Demo;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Firestore;
use Google\Service\Firestore\Document;

/**
 * Operaciones de Google Drive y Firestore usadas por la carga demo.
 */
class NubeDemo
{
    public const COLECCION_RESUMEN = 'demo_cargas';
    public const COLECCION_TABLA = 'tabla_posiciones';
    private const INTENTOS = 4;

    /**
     * Sube un archivo a la carpeta configurada de Drive y devuelve su id.
     */
    public function subirArchivo(string $nombre, string $contenido, string $mime): string
    {
        // Algunas subidas a Google se quedan sin respuesta de forma intermitente: se reintenta.
        for ($intento = 1; ; $intento++) {
            try {
                $archivo = app(Drive::class)->files->create(
                    new DriveFile(['name' => $nombre, 'parents' => [config('services.google_drive.folder_id')]]),
                    ['data' => $contenido, 'mimeType' => $mime, 'uploadType' => 'multipart', 'fields' => 'id', 'supportsAllDrives' => true]
                );

                return (string) $archivo->id;
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                if ($intento >= self::INTENTOS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Borra de la carpeta de Drive los archivos que subió la demo (logo y escudos), por nombre.
     */
    public function borrarArchivosDemo(): int
    {
        $carpeta = config('services.google_drive.folder_id');
        $respuesta = app(Drive::class)->files->listFiles([
            'q' => "'{$carpeta}' in parents and trashed = false and (name contains 'escudo-' or name contains 'liga-betplay-logo')",
            'fields' => 'files(id)', 'pageSize' => 200, 'supportsAllDrives' => true, 'includeItemsFromAllDrives' => true,
        ]);

        return $this->borrarArchivos(array_map(fn ($f) => (string) $f->id, $respuesta->getFiles()));
    }

    /**
     * Borra archivos de Drive; ignora los que ya no existen.
     * @param list<string> $ids
     */
    public function borrarArchivos(array $ids): int
    {
        $borrados = 0;
        foreach ($ids as $id) {
            try {
                app(Drive::class)->files->delete($id, ['supportsAllDrives' => true]);
                $borrados++;
            } catch (\Google\Service\Exception) {
                // El archivo ya no existe o no es accesible.
            }
        }

        return $borrados;
    }

    /**
     * Crea o reemplaza un documento de Firestore con id conocido.
     */
    public function guardarDocumento(string $coleccion, string $id, array $datos): void
    {
        app(Firestore::class)->projects_databases_documents->patch(
            $this->raiz() . "/{$coleccion}/{$id}",
            new Document(['fields' => $this->campos($datos)])
        );
    }

    /**
     * Borra todos los documentos de una colección de Firestore.
     */
    public function vaciarColeccion(string $coleccion): int
    {
        $documentos = app(Firestore::class)->projects_databases_documents;
        $borrados = 0;
        foreach ($documentos->listDocuments($this->raiz(), $coleccion, ['pageSize' => 300])->getDocuments() ?? [] as $documento) {
            $documentos->delete($documento->getName());
            $borrados++;
        }

        return $borrados;
    }

    private function raiz(): string
    {
        return sprintf(
            'projects/%s/databases/%s/documents',
            config('services.firebase.project_id'),
            config('services.firebase.firestore_database')
        );
    }

    /**
     * Convierte un arreglo asociativo al formato de campos de Firestore.
     * @return array<string,array<string,mixed>>
     */
    private function campos(array $datos): array
    {
        $campos = [];
        foreach ($datos as $clave => $valor) {
            $campos[$clave] = $this->valor($valor);
        }

        return $campos;
    }

    /**
     * @return array<string,mixed>
     */
    private function valor(mixed $valor): array
    {
        return match (true) {
            $valor === null => ['nullValue' => 'NULL_VALUE'],
            is_bool($valor) => ['booleanValue' => $valor],
            is_int($valor) => ['integerValue' => (string) $valor],
            is_float($valor) => ['doubleValue' => $valor],
            $valor instanceof \DateTimeInterface => ['timestampValue' => $valor->format('Y-m-d\TH:i:s\Z')],
            is_array($valor) && array_is_list($valor) => ['arrayValue' => ['values' => array_map(fn ($v) => $this->valor($v), $valor)]],
            is_array($valor) => ['mapValue' => ['fields' => $this->campos($valor)]],
            default => ['stringValue' => (string) $valor],
        };
    }
}
