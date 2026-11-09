<?php

namespace App\Models\Account;

use App\Models\PostgresModel;
use App\Models\Bet\Apuesta;
use App\Models\Payment\Transaccion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends PostgresModel
{
    protected $table = 'movimiento';
    protected $primaryKey = 'id_movimiento';
    protected $fillable = ['usuario_id', 'tipo', 'monto', 'saldo_disponible_resultante', 'saldo_retenido_resultante', 'referencia_idempotencia', 'transaccion_id', 'apuesta_id', 'descripcion', 'creado_en'];
    protected function casts(): array { return ['monto' => 'decimal:2', 'saldo_disponible_resultante' => 'decimal:2', 'saldo_retenido_resultante' => 'decimal:2', 'creado_en' => 'datetime']; }
    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class, 'usuario_id', 'usuario_id'); }
    public function transaccion(): BelongsTo { return $this->belongsTo(Transaccion::class, 'transaccion_id', 'id_transaccion'); }
    public function apuesta(): BelongsTo { return $this->belongsTo(Apuesta::class, 'apuesta_id', 'id_apuesta'); }
}
