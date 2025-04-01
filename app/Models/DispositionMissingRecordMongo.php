<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DispositionMissingRecordMongo extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'disposition_missing_record_mongos';

    protected $fillable = [
        'disposition_config_id',
        'data'
    ];
}
