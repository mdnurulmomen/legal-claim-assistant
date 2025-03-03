<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DispositionConfigMongo extends Model
{

    protected $connection = 'mongodb';
    protected $collection = 'disposition_config_mongos'; // Collection name

    protected $fillable = [
        'user_id',
        'uid',
        'conditions'
    ];
}
