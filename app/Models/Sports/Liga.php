<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liga extends MysqlModel
{
    protected $table = 'liga';
    protected $primaryKey = 'id_liga';
    protected $fillable = ['servicio_codigo', 'pais_codigo', 'nombre', 'logo_drive_id', 'activa'];
    protected function casts(): array { return ['activa' => 'boolean']; }
    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_codigo', 'codigo'); }
    public function pais(): BelongsTo { return $this->belongsTo(Pais::class, 'pais_codigo', 'codigo_iso'); }
    public function temporadas(): HasMany { return $this->hasMany(Temporada::class, 'liga_id', 'id_liga'); }
}
