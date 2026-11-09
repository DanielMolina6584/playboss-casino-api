<?php

namespace App\Service\Storage;

use App\Models\Market\RegistroAuditoria;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    public function registrarAutenticacion(?int $usuarioId, ?string $correo, bool $exitoso, string $ip, ?string $dispositivo, ?string $motivo = null, ?int $tokenId = null): void
    {
        try {
            RegistroAuditoria::query()->create([
                'event' => 'authentication',
                'user_id' => $usuarioId,
                'email_hash' => $correo ? hash('sha256', mb_strtolower(trim($correo))) : null,
                'success' => $exitoso,
                'ip' => $ip,
                'device' => $dispositivo ? mb_substr($dispositivo, 0, 250) : null,
                'reason' => $motivo,
                'context' => $tokenId ? ['token_id' => $tokenId] : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // La auditoría MongoDB no debe invalidar una autenticación SQL Server.
            Log::warning('No se pudo guardar el evento de autenticación en MongoDB.', [
                'exception' => $exception::class,
            ]);
        }
    }

    /** Evento administrativo/financiero genérico; nunca debe romper la operación principal. */
    public function registrarEvento(string $evento, ?int $usuarioId, array $contexto = []): void
    {
        try {
            RegistroAuditoria::query()->create([
                'event' => $evento,
                'user_id' => $usuarioId,
                'success' => true,
                'context' => $contexto,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo guardar el evento de auditoría en MongoDB.', ['event' => $evento, 'exception' => $exception::class]);
        }
    }
}
