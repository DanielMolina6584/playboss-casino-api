<?php

namespace App\Models\Sports;

use App\Models\MysqlModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends MysqlModel
{
    protected $table = 'pais';
    protected $primaryKey = 'codigo_iso';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['codigo_iso', 'nombre'];
    public function ciudades(): HasMany { return $this->hasMany(Ciudad::class, 'pais_codigo', 'codigo_iso'); }
}
