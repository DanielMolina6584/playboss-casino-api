<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsJson;
use App\Http\Requests\ActualizarPerfilRequest;
use App\Http\Requests\CambiarContrasenaRequest;
use App\Models\Usuario\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class PerfilController extends Controller
{
    use RespondsJson;

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
}
