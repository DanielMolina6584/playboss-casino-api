<?php

namespace App\Models\Payment;

use App\Models\PostgresModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoPago extends PostgresModel
{
    protected $table = 'evento_pago';
    protected $primaryKey = 'id_evento';
    protected $fillable = ['id_evento_proveedor', 'transaccion_id', 'tipo_evento', 'verificado', 'metadata', 'recibido_en'];
    protected function casts(): array { return ['verificado' => 'boolean', 'metadata' => 'array', 'recibido_en' => 'datetime']; }
    public function transaccion(): BelongsTo { return $this->belongsTo(Transaccion::class, 'transaccion_id', 'id_transaccion'); }
}
