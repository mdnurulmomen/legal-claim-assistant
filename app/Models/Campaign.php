<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Campaign extends Model
{

    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     *
     * Scoop Campaign ID == Making all queries filering by campaign id
     */

    public function reports()
    {
        return $this->HasMany(Report::class, 'campaign_id', 'campaign_id');
    }

    public function built_reports()
    {
        return $this->HasMany(ProfitSplitReport::class, 'campaign_id', 'id');
    }

    public function list()
    {
        return $this->belongsTo(DataList::class, 'list_id', 'uuid');
    }

    public function conversions()
    {
        return $this->HasMany(Conversions::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'workspace', 'workspace');
    }

    public function traffic_source()
    {
        return $this->hasOne(TrafficSources::class, 'id');
    }

    public function campaign_per_day()
    {
        return $this->hasMany(Report::class, 'campaign_id', 'campaign_id');/*
        return $this->hasManyThrough(Report::class, Campaign::class, 'list_id', 'campaign_id', 'uuid', 'campaign_id'); */
    }

    public function report_provider()
    {
        return $this->hasOne(ReportProviders::class, 'id', 'provider');
    }

    public function custom_cost()
    {
        return $this->hasOne(PostbackData::class, 'campaign_id', 'campaign_id');
    }

}
