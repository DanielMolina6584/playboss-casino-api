<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class SqlServerAuthenticatable extends Authenticatable
{
    protected $connection = 'sqlsrv';
}
