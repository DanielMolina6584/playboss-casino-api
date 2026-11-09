<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jornada extends MysqlModel
{
    protected $table = 'jornada';
    protected $primaryKey = 'id_jornada';
    protected $fillable = ['temporada_id', 'numero', 'fecha_inicio', 'fecha_fin'];
    protected function casts(): array { return ['fecha_inicio' => 'date', 'fecha_fin' => 'date']; }
    public function temporada(): BelongsTo { return $this->belongsTo(Temporada::class, 'temporada_id', 'id_temporada'); }
    public function partidos(): HasMany { return $this->hasMany(Partido::class, 'jornada_id', 'id_jornada'); }
}
