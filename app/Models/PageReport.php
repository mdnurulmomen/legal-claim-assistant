<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_setting_id',
        'saved_report_id'
    ];
}
