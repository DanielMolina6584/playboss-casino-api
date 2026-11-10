<?php

namespace App\Models\Market;

class MensajeContacto extends MongoDocument
{
    protected $table = 'contact_messages';
    protected $fillable = ['nombre', 'correo', 'asunto', 'mensaje', 'ip', 'user_agent', 'correo_enviado', 'created_at'];
    protected $casts = ['correo_enviado' => 'boolean', 'created_at' => 'datetime'];
    public $timestamps = false;
}
