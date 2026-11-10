<?php

namespace App\Service\Storage;

use App\Models\Sports\Equipo;
use App\Models\Sports\Liga;
use App\Service\Demo\EscudoSvg;
use App\Service\Demo\LigaColombiana;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imagen pública de equipos y ligas: el archivo de Drive (`*_drive_id`) servido por la API,
 * o, si no hay archivo o Drive falla, un escudo SVG generado con las siglas y colores del equipo.
 */
class ImagenCatalogo
{
    public static function urlEquipo(Equipo $e): string
    {
        return self::url('media.equipo', $e->id_equipo, $e->escudo_drive_id);
    }

    public static function urlLiga(Liga $l): string
    {
        return self::url('media.liga', $l->id_liga, $l->logo_drive_id);
    }

    private static function url(string $ruta, int $id, ?string $driveId): string
    {
        // `v` invalida la caché del navegador si cambia el archivo.
        return route($ruta, ['id' => $id] + ($driveId ? ['v' => substr(md5($driveId), 0, 8)] : []));
    }

    /** @return array{contenido:string,mime_type:string,generado:bool} */
    public function deEquipo(Equipo $e): array
    {
        $slug = Str::slug($e->nombre);
        $ref = collect(LigaColombiana::equipos())->first(fn (array $x) => Str::slug($x['nombre']) === $slug);

        return $this->resolver($e->escudo_drive_id, $slug, $ref['siglas'] ?? $this->siglas($e->nombre), $ref['colores'] ?? $this->colores($slug));
    }

    /** @return array{contenido:string,mime_type:string,generado:bool} */
    public function deLiga(Liga $l): array
    {
        $slug = Str::slug($l->nombre);

        return $this->resolver($l->logo_drive_id, $slug, $this->siglas($l->nombre), $this->colores($slug));
    }

    private function resolver(?string $driveId, string $slug, string $siglas, array $colores): array
    {
        if ($driveId) {
            try {
                $archivo = Cache::remember('media:drive:' . $driveId, 3600, fn () => app(DriveImageStorage::class)->descargar($driveId));

                return $archivo + ['generado' => false];
            } catch (Throwable $e) {
                Log::warning('No se pudo leer la imagen del catálogo en Drive.', ['exception' => $e::class]);
            }
        }

        return ['contenido' => EscudoSvg::generar($slug, $siglas, $colores), 'mime_type' => 'image/svg+xml', 'generado' => true];
    }

    private function siglas(string $nombre): string
    {
        $palabras = array_filter(preg_split('/\s+/', Str::ascii($nombre)), fn ($p) => strlen($p) > 2);
        $siglas = strtoupper(implode('', array_map(fn ($p) => $p[0], array_slice($palabras, 0, 3))));

        return $siglas !== '' ? $siglas : strtoupper(substr(Str::ascii($nombre), 0, 3));
    }

    /** Colores estables derivados del nombre. */
    private function colores(string $slug): array
    {
        $h = crc32($slug);

        return [sprintf('#%02X%02X%02X', 40 + $h % 120, 40 + ($h >> 8) % 120, 40 + ($h >> 16) % 120), '#FFFFFF'];
    }
}
