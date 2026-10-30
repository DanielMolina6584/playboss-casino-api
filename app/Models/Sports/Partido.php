<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partido extends MysqlModel
{
    public const ESTADOS_APOSTABLES = ['PROG', 'ENJ'];

    protected $table = 'partido';
    protected $primaryKey = 'id_partido';
    protected $fillable = ['jornada_id', 'equipo_local_id', 'equipo_visitante_id', 'estadio_id', 'fecha_hora', 'estado_codigo', 'goles_local', 'goles_visitante'];
    protected function casts(): array { return ['fecha_hora' => 'datetime']; }
    public function jornada(): BelongsTo { return $this->belongsTo(Jornada::class, 'jornada_id', 'id_jornada'); }
    public function local(): BelongsTo { return $this->belongsTo(Equipo::class, 'equipo_local_id', 'id_equipo'); }
    public function visitante(): BelongsTo { return $this->belongsTo(Equipo::class, 'equipo_visitante_id', 'id_equipo'); }
    public function estadio(): BelongsTo { return $this->belongsTo(Estadio::class, 'estadio_id', 'id_estadio'); }
    public function estado(): BelongsTo { return $this->belongsTo(EstadoPartido::class, 'estado_codigo', 'codigo'); }
}
