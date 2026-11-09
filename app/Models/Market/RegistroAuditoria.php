<?php

namespace App\Models\Market;

class RegistroAuditoria extends MongoDocument
{
    protected $table = 'audit_logs';
    protected $primaryKey = '_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['event', 'user_id', 'email_hash', 'success', 'ip', 'device', 'reason', 'context', 'created_at'];
    protected $casts = ['user_id' => 'integer', 'success' => 'boolean', 'created_at' => 'datetime'];
    public $timestamps = false;
}
