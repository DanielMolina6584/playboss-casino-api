<?php

namespace App\Models\Payment;

use App\Models\PostgresModel;

class MetodoPago extends PostgresModel
{
    protected $table = 'metodo_pago';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['codigo', 'nombre', 'activo'];
    protected function casts(): array { return ['activo' => 'boolean']; }
}
