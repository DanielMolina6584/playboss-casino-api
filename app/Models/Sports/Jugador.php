<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jugador extends MysqlModel
{
    protected $table = 'jugador';
    protected $primaryKey = 'id_jugador';
    protected $fillable = ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'fecha_nacimiento', 'pais_codigo', 'posicion_codigo', 'equipo_actual_id', 'dorsal', 'foto_drive_id'];
    protected function casts(): array { return ['fecha_nacimiento' => 'date']; }
    public function equipo(): BelongsTo { return $this->belongsTo(Equipo::class, 'equipo_actual_id', 'id_equipo'); }
    public function pais(): BelongsTo { return $this->belongsTo(Pais::class, 'pais_codigo', 'codigo_iso'); }
    public function posicion(): BelongsTo { return $this->belongsTo(Posicion::class, 'posicion_codigo', 'codigo'); }
}
