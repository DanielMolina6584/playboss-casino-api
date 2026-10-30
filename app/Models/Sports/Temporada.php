<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Temporada extends MysqlModel
{
    protected $table = 'temporada';
    protected $primaryKey = 'id_temporada';
    protected $fillable = ['liga_id', 'nombre', 'fecha_inicio', 'fecha_fin'];
    protected function casts(): array { return ['fecha_inicio' => 'date', 'fecha_fin' => 'date']; }
    public function liga(): BelongsTo { return $this->belongsTo(Liga::class, 'liga_id', 'id_liga'); }
    public function jornadas(): HasMany { return $this->hasMany(Jornada::class, 'temporada_id', 'id_temporada'); }
}
