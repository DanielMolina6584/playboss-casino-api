<?php

namespace App\Models\Bet;

use App\Models\PostgresModel;
use App\Models\Account\Cuenta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apuesta extends PostgresModel
{
    protected $table = 'apuesta';
    protected $primaryKey = 'id_apuesta';
    protected $fillable = ['usuario_id', 'monto', 'cuota_total', 'ganancia_potencial', 'estado_codigo', 'clave_idempotencia', 'creada_en'];
    protected function casts(): array { return ['monto' => 'decimal:2', 'cuota_total' => 'decimal:4', 'ganancia_potencial' => 'decimal:2', 'creada_en' => 'datetime', 'liquidada_en' => 'datetime']; }
    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class, 'usuario_id', 'usuario_id'); }
    public function estado(): BelongsTo { return $this->belongsTo(EstadoApuesta::class, 'estado_codigo', 'codigo'); }
    public function detalles(): HasMany { return $this->hasMany(ApuestaDetalle::class, 'apuesta_id', 'id_apuesta'); }
}
