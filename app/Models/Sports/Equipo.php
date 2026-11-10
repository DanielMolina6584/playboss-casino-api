<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipo extends MysqlModel
{
    protected $table = 'equipo';
    protected $primaryKey = 'id_equipo';
    protected $fillable = ['nombre', 'escudo_drive_id', 'ciudad_id', 'estadio_id', 'fecha_fundacion'];
    protected $appends = ['escudo_url'];
    public function getEscudoUrlAttribute(): string { return \App\Service\Storage\ImagenCatalogo::urlEquipo($this); }
    protected function casts(): array { return ['fecha_fundacion' => 'date']; }
    public function ciudad(): BelongsTo { return $this->belongsTo(Ciudad::class, 'ciudad_id', 'id_ciudad'); }
    public function estadio(): BelongsTo { return $this->belongsTo(Estadio::class, 'estadio_id', 'id_estadio'); }
}
