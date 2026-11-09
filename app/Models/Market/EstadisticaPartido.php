<?php

namespace App\Models\Market;

/**
 * Estadísticas de un partido finalizado (descanso, córners, tarjetas y tiros).
 * Se usan para liquidar los mercados que no dependen solo del marcador.
 */
class EstadisticaPartido extends MongoDocument
{
    public const CAMPOS = [
        'ht_local', 'ht_visitante', 'corners_local', 'corners_visitante', 'tarjetas_local', 'tarjetas_visitante',
        'tiros_local', 'tiros_visitante', 'tiros_puerta_local', 'tiros_puerta_visitante',
    ];

    protected $table = 'match_stats';
    protected $primaryKey = '_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['match_id', ...self::CAMPOS, 'updated_at'];
    protected $casts = ['match_id' => 'integer', 'updated_at' => 'datetime'];
}
