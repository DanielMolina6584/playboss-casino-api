<?php

namespace App\Models\Market;

use MongoDB\Laravel\Eloquent\Model;

abstract class MongoDocument extends Model
{
    protected $connection = 'mongodb';
}
