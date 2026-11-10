<?php

namespace App\Models\Account;

use App\Models\PostgresModel;

class LimiteJuego extends PostgresModel
{
    public const CAMPOS = ['deposito_diario', 'deposito_semanal', 'deposito_mensual', 'apuesta_diario', 'apuesta_semanal', 'apuesta_mensual'];

    protected $table = 'limite_juego';
    protected $primaryKey = 'usuario_id';
    public $incrementing = false;
    protected $fillable = ['usuario_id', 'autoexcluido_hasta', 'actualizado_en', ...self::CAMPOS];
    protected function casts(): array
    {
        return array_fill_keys(self::CAMPOS, 'decimal:2') + ['autoexcluido_hasta' => 'datetime', 'actualizado_en' => 'datetime'];
    }
}
