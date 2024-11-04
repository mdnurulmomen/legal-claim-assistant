<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Observers\DataListObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\ContentPlanning\Entities\ContentItem;
use Modules\MonetizeBot\Entities\CampaignShoots;

class DataList extends Model
{
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // self::observe(DataListObserver::class);
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'datalists';
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
        'verticals' => 'array',
        'sheetnames' => 'array',
        'insight' => 'array',
    ];

    protected $appends = ['totalEmailLeads'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     *
     * Scoop User ID == Making all queries filering by user id
     */

    public function allData()
    {
        return $this->hasMany(Data::class, 'list_id');
    }

    public function validData()
    {
        return $this->hasMany(Data::class, 'list_id')->where('updated_at', '>=', Carbon::now()->subMonth(3));
    }

    public function expiredData()
    {
        return $this->hasMany(Data::class, 'list_id')->where('updated_at', '<=', Carbon::now()->subMonth(3));
    }

    public function blackData()
    {
        return $this->hasMany(Data::class, 'list_id')->where('lead_score', 0);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function partner()
    {
        return $this->hasOne(Partner::class, 'user_id', 'user_id');
    }

    public function campaign()
    {
        return $this->hasMany(Campaign::class, 'list_id', 'uuid');
    }

    public function check_campaigns()
    {
        return $this->doesntHave('campaign');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'list_id', 'id');
    }

    public function internalComments()
    {
        return $this->hasMany(InternalComment::class, 'list_id', 'id');
    }

    public function countComments()
    {
        return $this->comments()->count();
    }

    public function countTotalComments()
    {
        return $this->comments()->count() + $this->internalComments()->count();
    }

    public function specialComments()
    {
        return $this->hasOne(Comment::class, 'list_id', 'id')->where('is_special', 1);
    }

    public function reports()
    {
        return $this->hasManyThrough(Report::class, Campaign::class, 'list_id', 'campaign_id', 'uuid', 'campaign_id')->select(['reports.id', 'reports.cost', 'reports.impressions', 'reports.revenue'])->where('reports.deleted', 0);
    }

    public function reports_per_day()
    {
        return $this->hasManyThrough(Report::class, Campaign::class, 'list_id', 'campaign_id', 'uuid', 'campaign_id');
    }

    public function recent_reports()
    {
        return $this->HasMany(ProfitSplitReport::class, 'list_id', 'id');
    }

    public function daily_reports()
    {
        return $this->HasMany(ProfitSplitReport::class, 'list_id', 'id');
    }

    public function automation_comment()
    {
        return $this->HasMany(Comment::class, 'list_id', 'id');
    }

    public function listresult()
    {
        return $this->belongsToMany(DataList::class);
    }

    public function has_listresult()
    {
        return $this->hasOne(lignes_listsresult::class, 'list_id', 'id');
    }

    public function reports_statics($tag, $workspace, $campaigns_id)
    {

        $cache_key = $workspace;
        $cache_tag = ['list_cost_revenue', 'model_reports_cost_revenue_' . $workspace];
        $cache_time = $workspace == "admin" ? 60 : 3600;

        #
        $reports_statics_obj = Cache::tags($cache_tag)->remember(md5('model_reports_cost_revenue_' . $cache_key . 'taglist_' . $tag), $cache_time, function () use ($campaigns_id) {

            return DB::table('reports')
                // ->select('cost', 'revenue')
               // ->where('reports.deleted', 0)
                ->whereIn('campaign_id', $campaigns_id)
                ->selectRaw('SUM(reports.cost)as reports_sum_cost')
                ->selectRaw('SUM(reports.revenue)as reports_sum_revenue')
                ->selectRaw("SUM(reports.uniqueVisits)as visits")
                ->get();
        });

        return  $reports_statics_obj;
    }


    public function validation_costs(){
        return $this->hasMany(ListValidationCost::class, 'list_id', 'id');
    }

    public function phone_validation_costs(){
        return $this->hasMany(ListValidationCost::class, 'list_id', 'id')->where('type', 1);
    }

    public function email_validation_costs(){
        return $this->hasMany(ListValidationCost::class, 'list_id', 'id')->where('type', 2);
    }

    public function email_v_costs(){
        return $this->hasMany(ListValidationCost::class, 'list_id', 'id')->where('type', 2);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manged_by', 'id');
    }

    public function parent_list()
    {
        $parent = $this->hasOneThrough(DataList::class, MergedListsPivot::class, 'child_list_id', 'id', 'id', 'parent_list_id');
        return $parent->clone()->where('merged_lists_pivots.sub_merged', 1)->first() ? $parent->where('merged_lists_pivots.sub_merged', 1) : $parent->where('merged_lists_pivots.sub_merged', NULL);
    }

    public function master_parent_list()
    {
        $parent = $this->hasOneThrough(DataList::class, MergedListsPivot::class, 'child_list_id', 'id', 'id', 'parent_list_id');
        return $parent->clone()->where('merged_lists_pivots.sub_merged', 1)->first() ? $parent->orderBy('created_at', 'DESC') : $parent->where('merged_lists_pivots.sub_merged', NULL);
    }

    public function parent_lists()
    {
        return $this->hasManyThrough(DataList::class, MergedListsPivot::class, 'child_list_id', 'id', 'id', 'parent_list_id');
    }

    public function child_lists()
    {
        return $this->belongsToMany(DataList::class, 'merged_lists_pivots', 'parent_list_id', 'child_list_id')->withPivot(["sub_merged"])->groupBy('child_list_id');
    }

    public function child_counts()
    {
        return $this->belongsToMany(DataList::class, 'merged_lists_pivots', 'parent_list_id', 'child_list_id')->groupBy('child_list_id')->selectRaw('merged_lists_pivots.id as id, SUM(JSON_EXTRACT(`insight`,"$.uploaded")) AS "uploaded_amount", SUM(JSON_EXTRACT(`insight`,"$.invalid")) AS "invalid_amount", SUM(JSON_EXTRACT(`insight`,"$.duplicates")) AS "duplicates_amount"');
    }

    public function email_child_counts()
    {
        return $this->belongsToMany(DataList::class, 'merged_lists_pivots', 'parent_list_id', 'child_list_id')->groupBy('child_list_id')->selectRaw('merged_lists_pivots.id as id, SUM(JSON_EXTRACT(`insight`,"$.uploaded")) AS "uploaded_amount", SUM(JSON_EXTRACT(`insight`,"$.email_insight.invalid")) AS "invalid_amount", SUM(JSON_EXTRACT(`insight`,"$.email_insight.duplicates")) AS "duplicates_amount"');
    }

    public function is_expired()
    {
        $siteSettings = SiteSettings::first();
        return $this->validated_at ? $this->validated_at < Carbon::now()->sub($siteSettings->data_block_period_type, $siteSettings->data_block_period_num) : false;
    }

    public function dataenriched()
    {
        return $this->hasOne(enriched_list::class, 'list_id', 'id');
    }

    public function vertical_lists()
    {
        return $this->belongsToMany(DataList::class, 'list_copies_pivot', 'parent_list_id', 'copied_list_id', 'id');
    }

    public function hasOriginalList()
    {
        return $this->hasOneThrough(DataList::class, ListCopiesModel::class, 'copied_list_id', 'id', 'id', 'parent_list_id');
    }

    public function contentItem()
    {
        return $this->belongsTo(ContentItem::class, 'id', 'list_id');
    }

    public function listProviders()
    {
        //get the list of reportproviders through campaigns model
        return $this->hasManyThrough(ReportProviders::class, Campaign::class, 'list_id', 'id', 'uuid', 'provider')->groupBy('report_providers.type');
    }

    public function getTotalEmailLeadsAttribute()
    {
        //get the list of reportproviders through campaigns model
        return $this->insight['email_insight']['active'] ?? 0;
    }

    public function emailUsegesHistories()
    {
        return $this->hasMany(ListEmailUsegesHistory::class, 'list_id', 'id');
    }

    public function campaignShoots()
    {
        return $this->hasMany(CampaignShoots::class, 'list_uuid', 'uuid');
    }

    public function additionalFiles()
    {
        return $this->hasMany(AdditionalListFiles::class, 'list_id', 'id');
    }

    public function addLeads()
    {
        return $this->hasMany(TemporaryListUploads::class, 'tag', 'tag');
    }

    public function forwardedList()
    {
        return $this->hasOne(ListForwardHistory::class, 'list_id', 'id');
    }

}
