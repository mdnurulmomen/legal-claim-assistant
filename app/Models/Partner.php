<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Partner extends Model
{
    use HasFactory;

    /** Connection */
    protected $connection = 'mysql';

    protected $guarded = [];

    protected $casts = [
        'data_upload_limit' => 'array',
        'vertical' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function leadsCountries()
    {
        return $this->belongsToMany(Country::class);
    }

    //why 7? check this later
    public function result_periods()
    {
        return $this->hasOne(ListResultPeriodSettings::class,'interval',7);
    }

    //relation on list result period
    public function result_period()
    {
        return $this->hasOne(ListResultPeriodSettings::class,'interval', 'listresult_period');
    }

    public function getMonthlyNetAttribute($value)
    {
        if( is_null($value) ){
            return config('monetize.payment_term_default');
        }

        return $value;
    }

    public function getUploadLimit()
    {
        $limitData = $this->data_upload_limit;
        $globalLimit = SiteSettings::first('partner_data_upload_limit')->partner_data_upload_limit;
        if( empty($limitData) ){
            $limitData = $globalLimit;
        } else {
             $limitData = ['min' => is_null($limitData['min']) ? $globalLimit['min'] : $limitData['min'], 'max' => is_null($limitData['max']) ? $globalLimit['max'] : $limitData['max']];
        }

        return $limitData;

    }

}
