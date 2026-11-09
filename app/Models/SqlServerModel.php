<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class SqlServerModel extends Model
{
    protected $connection = 'sqlsrv';
    public $timestamps = false;
}
