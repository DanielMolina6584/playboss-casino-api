<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ContactoRequest;
use App\Models\Market\MensajeContacto;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Formulario público de contacto: guarda en MongoDB y avisa por correo. */
class ContactoController extends Controller
{
    use RespondsJson;

    public function store(ContactoRequest $r): JsonResponse
    {
        $d = $r->validated();
        $guardado = false;
        $enviado = false;

        try {
            $mensaje = MensajeContacto::query()->create($d + [
                'ip' => $r->ip(),
                'user_agent' => mb_substr((string) $r->userAgent(), 0, 250),
                'correo_enviado' => false,
                'created_at' => now(),
            ]);
            $guardado = true;
        } catch (Throwable $e) {
            Log::warning('No se pudo guardar el mensaje de contacto en MongoDB.', ['exception' => $e::class]);
        }

        try {
            $destino = config('playboss.contact.to');
            if (is_string($destino) && $destino !== '') {
                // Sin saltos de línea en cabeceras: evita inyección de encabezados.
                $asunto = '[Contacto PlayBoss] ' . preg_replace('/[\r\n]+/', ' ', $d['asunto']);
                $cuerpo = "De: {$d['nombre']} <{$d['correo']}>\n\n{$d['mensaje']}";
                Mail::raw($cuerpo, fn ($m) => $m->to($destino)->replyTo($d['correo'], preg_replace('/[\r\n]+/', ' ', $d['nombre']))->subject($asunto));
                $enviado = true;
                if ($guardado) {
                    $mensaje->forceFill(['correo_enviado' => true])->save();
                }
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el correo de contacto.', ['exception' => $e::class]);
        }

        if (!$guardado && !$enviado) {
            throw new ReglaNegocioException('No pudimos registrar tu mensaje. Intenta de nuevo más tarde.', 503);
        }

        return $this->ok([], 201, ['Mensaje recibido. Te responderemos pronto.']);
    }
}
