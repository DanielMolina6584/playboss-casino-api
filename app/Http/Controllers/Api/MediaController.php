<?php

namespace App\Http\Controllers\Api;

use App\Models\Sports\Equipo;
use App\Models\Sports\Liga;
use App\Service\Storage\ImagenCatalogo;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/** Imágenes públicas del catálogo (escudos y logos) para usar directo en <img src>. */
class MediaController extends Controller
{
    public function __construct(private readonly ImagenCatalogo $imagenes)
    {
    }

    public function escudo(int $id): Response
    {
        return $this->responder($this->imagenes->deEquipo(Equipo::query()->findOrFail($id)));
    }

    public function logo(int $id): Response
    {
        return $this->responder($this->imagenes->deLiga(Liga::query()->findOrFail($id)));
    }

    private function responder(array $imagen): Response
    {
        $mime = $imagen['mime_type'];
        // Solo imágenes: nunca reflejar otro tipo de contenido (p. ej. HTML) desde Drive.
        if (!str_starts_with($mime, 'image/')) {
            $mime = 'application/octet-stream';
        }
        $cabeceras = [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=' . ($imagen['generado'] ? 300 : 86400),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ];

        return response($imagen['contenido'], 200, $cabeceras);
    }
}
