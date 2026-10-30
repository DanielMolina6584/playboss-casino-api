<?php

namespace App\Models\Usuario;

use App\Models\SqlServerModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodigoRecuperacion extends SqlServerModel
{
    protected $table = 'codigo_recuperacion';
    protected $primaryKey = 'id_codigo';
    public $timestamps = false;
    protected $hidden = ['codigo_hash'];
    protected $fillable = ['usuario_id', 'codigo_hash', 'fecha_expiracion', 'usado_en'];
    protected function casts(): array
    {
        return ['fecha_generacion' => 'datetime', 'fecha_expiracion' => 'datetime', 'usado_en' => 'datetime'];
    }
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }
}
