<?php

namespace App\Models\Market;

class ReglaApuesta extends MongoDocument
{
    protected $table = 'bet_rules';
    protected $primaryKey = '_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['code', 'market_code', 'min_stake', 'max_stake', 'max_payout', 'min_age', 'active', 'effective_from', 'version'];
    protected $casts = ['min_stake' => 'decimal:2', 'max_stake' => 'decimal:2', 'max_payout' => 'decimal:2', 'min_age' => 'integer', 'active' => 'boolean', 'effective_from' => 'datetime', 'version' => 'integer'];
}
