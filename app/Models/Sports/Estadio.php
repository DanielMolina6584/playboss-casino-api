<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estadio extends MysqlModel
{
    protected $table = 'estadio';
    protected $primaryKey = 'id_estadio';
    protected $fillable = ['nombre', 'ciudad_id', 'capacidad', 'direccion'];
    public function ciudad(): BelongsTo { return $this->belongsTo(Ciudad::class, 'ciudad_id', 'id_ciudad'); }
}
