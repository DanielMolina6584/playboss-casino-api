<?php

namespace App\Models\Account;

use App\Models\PostgresModel;
use App\Models\Bet\Apuesta;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuenta extends PostgresModel
{
    protected $table = 'cuenta';
    protected $primaryKey = 'usuario_id';
    public $incrementing = false;
    protected $fillable = ['usuario_id', 'saldo_disponible', 'saldo_retenido', 'moneda'];
    protected function casts(): array { return ['saldo_disponible' => 'decimal:2', 'saldo_retenido' => 'decimal:2']; }
    public function movimientos(): HasMany { return $this->hasMany(Movimiento::class, 'usuario_id', 'usuario_id'); }
    public function apuestas(): HasMany { return $this->hasMany(Apuesta::class, 'usuario_id', 'usuario_id'); }
}
