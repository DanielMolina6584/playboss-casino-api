<?php

namespace App\Models\Market;

class Mercado extends MongoDocument
{
    protected $collection = 'markets';
    protected $primaryKey = '_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['match_id', 'code', 'name', 'status', 'selections', 'version', 'updated_at'];
    protected $casts = ['selections' => 'array', 'version' => 'integer', 'updated_at' => 'datetime'];
}
