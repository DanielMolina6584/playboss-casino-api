<?php

namespace App\Models\Payment;

use App\Models\PostgresModel;
use App\Models\Account\Cuenta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaccion extends PostgresModel
{
    protected $table = 'transaccion';
    protected $primaryKey = 'id_transaccion';
    protected $fillable = ['usuario_id', 'metodo_pago_codigo', 'tipo', 'monto', 'moneda', 'estado', 'clave_idempotencia', 'referencia_externa', 'url_redireccion', 'fecha_hora'];
    protected function casts(): array { return ['monto' => 'decimal:2', 'fecha_hora' => 'datetime', 'confirmada_en' => 'datetime']; }
    public function cuenta(): BelongsTo { return $this->belongsTo(Cuenta::class, 'usuario_id', 'usuario_id'); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_codigo', 'codigo'); }
    public function eventos(): HasMany { return $this->hasMany(EventoPago::class, 'transaccion_id', 'id_transaccion'); }
}
