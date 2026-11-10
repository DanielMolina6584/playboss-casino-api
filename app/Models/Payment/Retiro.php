<?php

namespace App\Models\Payment;

use App\Models\PostgresModel;

class Retiro extends PostgresModel
{
    protected $table = 'retiro';
    protected $primaryKey = 'id_retiro';
    protected $fillable = ['usuario_id', 'monto', 'moneda', 'estado', 'clave_idempotencia', 'banco', 'tipo_cuenta', 'numero_cuenta', 'titular', 'documento_titular', 'admin_id', 'motivo_rechazo', 'solicitado_en', 'resuelto_en'];
    protected function casts(): array { return ['monto' => 'decimal:2', 'solicitado_en' => 'datetime', 'resuelto_en' => 'datetime']; }
}
