<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ciudad extends MysqlModel
{
    protected $table = 'ciudad';
    protected $primaryKey = 'id_ciudad';
    protected $fillable = ['nombre', 'pais_codigo'];
    public function pais(): BelongsTo { return $this->belongsTo(Pais::class, 'pais_codigo', 'codigo_iso'); }
}
