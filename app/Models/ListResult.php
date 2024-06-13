<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListResult extends Model
{
    use HasFactory;
    
    protected $table = 'listresults';

    public function data()
    {
        // return $this->hasMany(listresult_data::class,'result_id');
    }

    public function lists()
    {
        // return $this->belongsToMany(DataList::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoices::class)->latest();
    }

    public function invoice()
    {
        return $this->belongsToMany(Invoices::class)->latest();
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
