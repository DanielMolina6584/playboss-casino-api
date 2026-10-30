<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class PostgresModel extends Model
{
    protected $connection = 'pgsql';
    public $timestamps = false;
}
