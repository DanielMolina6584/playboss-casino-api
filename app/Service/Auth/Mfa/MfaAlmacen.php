<?php

namespace App\Service\Auth\Mfa;

use App\Models\Usuario\Usuario;

/** Persistencia del 2FA (SQL Server). Interfaz para poder probar la lógica sin base de datos. */
interface MfaAlmacen
{
    public function usuario(int $usuarioId): ?Usuario;

    /** @return array{secreto_cifrado:string,confirmado_en:?\DateTimeInterface,ultimo_contador:?int}|null */
    public function obtener(int $usuarioId): ?array;

    public function guardarPendiente(int $usuarioId, string $secretoCifrado): void;

    /** Marca el 2FA como activo, fija el contador usado y reemplaza los códigos de recuperación. */
    public function confirmar(int $usuarioId, int $contador, array $hashesRecuperacion): void;

    /** Avance atómico del contador; false si otro request ya lo usó (anti-reutilización/carrera). */
    public function avanzarContador(int $usuarioId, int $contador): bool;

    public function reemplazarRecuperacion(int $usuarioId, array $hashes): void;

    /** Marca un código como usado; false si no existe o ya se usó. */
    public function consumirRecuperacion(int $usuarioId, string $hash): bool;

    public function recuperacionRestantes(int $usuarioId): int;

    public function eliminar(int $usuarioId): void;

    /** @return array{0:string,1:int} [token en texto plano, id del token] */
    public function emitirToken(Usuario $usuario): array;

    /** Revoca los tokens Sanctum del usuario (menos $excepto). */
    public function revocarSesiones(int $usuarioId, ?int $excepto = null): void;
}
