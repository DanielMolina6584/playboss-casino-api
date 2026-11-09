<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ActualizarPerfilRequest;
use App\Http\Requests\CambiarContrasenaRequest;
use App\Http\Requests\SubirFotoPerfilRequest;
use App\Models\Usuario\Usuario;
use App\Service\SvcFotoPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PerfilController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly SvcFotoPerfil $svcFotoPerfil)
    {
    }

    public function show(): JsonResponse
    {
        return $this->ok(['usuario' => request()->user()->datosPublicos()]);
    }

    public function update(ActualizarPerfilRequest $r): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $r->user();
        $usuario->loadMissing('perfil');
        $usuario->perfil->fill($r->validated())->save();

        return $this->ok(['usuario' => $usuario->fresh()->datosPublicos()]);
    }

    public function cambiarContrasena(CambiarContrasenaRequest $r): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $r->user();
        $usuario->loadMissing('credencial');
        if (!Hash::check($r->input('contrasena_actual'), $usuario->getAuthPassword())) {
            return $this->fail(['La contraseña actual no es correcta.'], 422);
        }
        $usuario->credencial->contrasena_hash = Hash::make($r->input('contrasena'));
        $usuario->credencial->save();
        // Cierra las demás sesiones, conserva la actual.
        $usuario->tokens()->where('id', '!=', $usuario->currentAccessToken()->id)->delete();

        return $this->ok([], 200, ['Contraseña actualizada.']);
    }

    /**
     * Sube o reemplaza la foto de perfil (se guarda en Google Drive).
     */
    public function subirFoto(SubirFotoPerfilRequest $r): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $r->user();
        if (!$this->svcFotoPerfil->guardar($usuario->id_usuario, $r->file('foto'))) {
            return $this->fail(['No pudimos guardar tu foto de perfil. Intenta de nuevo.'], 502);
        }

        return $this->ok(['usuario' => $usuario->fresh()->datosPublicos()], 200, ['Foto de perfil actualizada.']);
    }

    /**
     * Devuelve la imagen de la foto de perfil, leída desde Google Drive.
     */
    public function foto(): Response
    {
        $foto = $this->svcFotoPerfil->descargar(request()->user()->id_usuario);
        if (!$foto) {
            return $this->fail(['No tienes foto de perfil.'], 404);
        }

        return response($foto['contenido'], 200, [
            'Content-Type' => $foto['mime_type'],
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
