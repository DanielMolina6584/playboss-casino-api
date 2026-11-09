<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class MysqlModel extends Model
{
    protected $connection = 'mysql';
    public $timestamps = false;
}
