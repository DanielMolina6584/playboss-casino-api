<?php

namespace App\Models\Bet;

use App\Models\PostgresModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApuestaDetalle extends PostgresModel
{
    protected $table = 'apuesta_detalle';
    protected $primaryKey = 'id_detalle';
    protected $fillable = ['apuesta_id', 'partido_id_mysql', 'mercado_id_mongodb', 'seleccion_codigo', 'seleccion_etiqueta', 'cuota_congelada', 'resultado'];
    protected function casts(): array { return ['cuota_congelada' => 'decimal:4']; }
    public function apuesta(): BelongsTo { return $this->belongsTo(Apuesta::class, 'apuesta_id', 'id_apuesta'); }
}
