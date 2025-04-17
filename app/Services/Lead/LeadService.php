<?php

namespace App\Services\Lead;

use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use App\Jobs\GlobalPostBackTriggerJob;
use App\Models\Integration;
use App\Models\LeadLog;
use App\Models\LeadReport;
use App\Models\PageSetting;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Models\User;
use App\Services\ReportingService;
use App\Traits\FormatterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadService extends ReportingService
{
    use FormatterTrait;

    protected $validOrderByColumns = [
        'email',
        'phone',
        'revenue',
        'profit',
        'affiliate_payout',
        'affiliate_margin'
    ];

    /**
     * Formats the order by and order in parameters from the request.
     *
     * @param Request $request
     * @return array The formatted order by and order in parameters.
     */
    public function formatLeadOrderByIn(Request $request): array
    {
        $orderBy = $request->order_by;
        $orderIn = $request->order_in;

        if(empty($orderBy) || empty($orderIn)) {
            return ['', ''];
        }

        if (! in_array($orderIn, ['asc', 'desc'])) {
            $orderIn = '';
        }

        if (! in_array($orderBy, $this->validOrderByColumns)) {
            $orderBy = '';
        }

        return [$orderBy, $orderIn];
    }

    /**
     * Formats an array of headers into a sorted and formatted array.
     *
     * @param Collection $headers
     * @return array
     */
    public function formatHeaders(Collection $headers): array
    {
        $serialization = $this->getSortFields();

        return collect($headers)->sortBy(function ($item) use ($serialization) {
            $index = array_search($item, $serialization);
            return $index === false ? PHP_INT_MAX : $index;
        })
        ->map(function ($header) use ($serialization) {
            return [
                'field' => $header,
                'headerName' => $header,
                'minWidth' => 200,
                'hide' => ! in_array($header, $serialization),
                'editable' => true,
                'sortable' => in_array($header, $this->validOrderByColumns),
            ];
        })
        ->values()
        ->all();
    }

    /**
     * Formats an array of headers into a sorted and formatted array for the
     * columns only.
     *
     * @param Collection $headers
     * @return array
     */
    public function formatHeadersOnly(Collection $headers): array
    {
        $serialization = $this->getSortFields();

        return collect($headers)
                    ->sortBy(function ($item) use ($serialization) {
                        $index = array_search($item, $serialization);
                        return $index === false ? PHP_INT_MAX : $index;
                    })
                    ->map(function (string $header) {
                        return [
                            'value' => $header,
                            'label' => $header
                        ];
                    })
                    ->values()
                    ->all();
    }

    /**
     * Retrieves performance data for the given request parameters.
     *
     * @param Builder $baseQuery
     * @param Request $request
     */
    public function getLeadTotals(Builder $baseQuery, Request $request)
    {
        $totals = $baseQuery->leftJoin('lead_reports', 'lead_reports.lead_id', '=', 'platform_datas.id')
                        ->selectRaw('
                            SUM(lead_reports.lead_revenue) as total_revenue,
                            SUM(lead_reports.lead_profit) as total_profit,
                            SUM(lead_reports.affiliate_payout) as avg_affiliate_payout,
                            (SUM(lead_reports.affiliate_margin) / COUNT(DISTINCT platform_datas.id)) as avg_affiliate_margin
                        ')
                        ->first();

        $totals->total_revenue = (float) $totals->total_revenue;
        $totals->total_profit = (float) $totals->total_profit;
        $totals->avg_affiliate_payout = (float) $totals->avg_affiliate_payout;
        $totals->avg_affiliate_margin = (float) $totals->avg_affiliate_margin;

        return $totals;
    }

    /**
     * Formats the search query for the leads list.
     *
     * @param Request $request
     * @param Builder $query
     * @return Builder
     */
    public function formatSearchColumn(Request $request, Builder $query): Builder
    {
        $searchText = strtolower($request->search_txt);

        return $query->where(function ($query) use ($searchText) {

            $searchCol = null;

            if (substr($searchText, 0, 2) === '+1' || is_numeric($searchText) && strlen($searchText) > 9 && strlen($searchText) < 12) {
                try {
                    $searchText = phone($searchText, 'US')->formatE164();
                    $searchCol = 'platform_datas.phone';
                } catch (\Throwable $th) {
                    //throw $th;
                }
            } else if (filter_var($searchText, FILTER_VALIDATE_EMAIL)) {
                $searchCol = 'platform_datas.email';
            }

            if ($searchCol) {
                return $query->where($searchCol, $searchText);
            }

            if (strlen($searchText) === 10 && PlatformData::where('platform_datas.affm_lead_id', $searchText)->exists()) {
                return $query->where('platform_datas.affm_lead_id', $searchText);
            }

            if(PlatformData::where('platform_datas.affid', $searchText)->exists()) {
                return $query->where('platform_datas.affid', $searchText);
            }

            return $query->whereRaw('LOWER(datas) like ?', ["%{$searchText}%"]);

                // ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.first_name"))) LIKE ?', ["%{$searchText}%"])
                // ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.last_name"))) LIKE ?', ["%{$searchText}%"]);
        });
    }

    /**
     * Returns an array of sort fields used for sorting lead data.
     *
     * @return array
     */
    public function getSortFields(): array
    {
        return [
            "affid",
            "first_name",
            "last_name",
            "phone",
            "email",
            "affm_source_id",
            "clickId",
            "zip_code",
            "attorney",
            "ip_address",
            "list_id",
            "state",
            "city",
            // "injury",
            "description",
            "jornaya_leadid",
            "transaction_id",
            "user_agent",
            "trusted_form_cert_id",
            "trusted_form_url",
            // "trusted_form_cert_url",
            "cancer",
            // "device_type",
            "optin_date",
            "page_source",
            "device",
            "fb_click_id",
            "utm_content",
            "pageurl"
        ];
    }

    /**
     * Formats the Excel filters from the request.
     *
     * @param Request $request
     * @return array
     */
    public function formatExcelFilters(Request $request)
    {
        $filters = $request->input('excel_filters', '');
        if(empty($filters)){
            return [];
        }

        $formattedFilters = [];
        $filters = json_decode($filters, true);

        foreach($filters as $filter){
            $terms = match($filter['column']){
                'buyer_integration' => [
                    'column' => "integrations.name",
                    'values' => $filter['values']
                ],
                'buyer_name' => [
                    'column' => "buyers.name",
                    'values' => $filter['values']
                ],
                'affiliate_name' => [
                    'column' => "users.name",
                    'values' => $filter['values']
                ],
                'lead_status', 'phone', 'email', 'affid' => [
                    'column' => "platform_datas." . $filter['column'],
                    'values' => $filter['values']
                ],
                default => $this->makeConditionWithoutOperator($filter['column'], $filter['values'])
            };
            $formattedFilters[] = $terms;
        }

        return $formattedFilters;
    }

    /**
     * Creates a condition array with the given column and values,
     * with the 'is_json_column' key set to true. The values are
     * transformed to lowercase.
     *
     * @param string $column
     * @param array $values
     * @return array
     */
    public function makeConditionWithoutOperator($column, array $values = [])
    {
        $values = array_map('strtolower', $values);

        return [
            'column' => $column,
            'values' => $values,
            'is_json_column' => true
        ];
    }

    /**
     * Formats the filters from the request.
     *
     * @param Request $request
     * @return array
     */
    public function formatFilters(Request $request): array
    {
        $filters = $request->input('filters', '');
        if(empty($filters)){
            return [[], []];
        }

        $filters = is_string($filters) ? json_decode($filters, true) : $filters;
        $formattedFilters = [];
        $relationalConditions = [];

        foreach($filters as $value){
            $conditions = [];
            $relationalTerms = [];

            foreach($value as $item){
                if(empty($item['column']) || empty($item['rule']) || empty($item['value'])){
                    continue;
                }

                if(in_array($item['column'], ['list_name', 'buyer_name', 'buyer_integration', 'affiliate_name', 'affid', 'lead_status', 'phone', 'email', 'affm_lead_id'])){
                    $relationalTerms[] = $this->formatAdvanceConditionToSql($item);
                    continue;
                }

                $conditions[] = $this->convertConditionToSql($item);
            }

            if(count($conditions) > 0){
                $formattedFilters[] = $conditions;
            }

            if(count($relationalTerms) > 0){
                $relationalConditions[] = $relationalTerms;
            }
        }

        return [$relationalConditions, $formattedFilters];
    }

    /**
     * Converts a condition array to a SQL condition array.
     *
     * @param array $conditions The condition array to be converted. It should have the following keys:
     *                         - 'rule': The rule of the condition.
     *                         - 'column': The column of the condition.
     *                         - 'value': The value of the condition.
     * @return array
     */
    public function convertConditionToSql(array $conditions, bool $isRelational = false): array
    {
        $condition = match($conditions['rule']){
            'contains' => $this->makeConditions($conditions['column'], 'like', ('%' . $conditions['value'] . '%'), $isRelational),
            'does_not_contain' => $this->makeConditions($conditions['column'], 'not like', ('%' . $conditions['value'] . '%'), $isRelational),
            'begins_with' => $this->makeConditions($conditions['column'], 'like', ($conditions['value'] . '%'), $isRelational),
            'does_not_begin_with' => $this->makeConditions($conditions['column'], 'not like', ($conditions['value'] . '%'), $isRelational),
            'greater_than' => $this->makeConditions($conditions['column'], '>', $conditions['value'], $isRelational),
            'less_than' => $this->makeConditions($conditions['column'], '<', $conditions['value'], $isRelational),
            'equals' => $this->makeConditions($conditions['column'], '=', $conditions['value'], $isRelational),
            'not_equals' => $this->makeConditions($conditions['column'], '!=', $conditions['value'], $isRelational),
            'equals_any' => $this->makeConditions($conditions['column'], '=', $conditions['value'], $isRelational),
            'exists' => $this->makeConditions($conditions['column'], 'exists', null, $isRelational),
            'does_not_exist' => $this->makeConditions($conditions['column'], 'does_not_exist', null, $isRelational),
            default => []
        };
        return $condition;
    }

    /**
     * Creates a condition array with the given column, operator, and optional value.
     *
     * @param string $column
     * @param string $operator
     * @param string|int|null $value
     * @return array
     */
    public function makeConditions($column, string $operator, string | int | null $value = '', bool $isRelational = false): array
    {
        return [
            'column' => $isRelational ? $column : ("platform_datas.datas->" . $column),
            'operator' => $operator,
            'value' => $value
        ];
    }

    /**
     * Filter data for export based on columns to keep.
     *
     * @param array $data
     * @return array
     */
    public function filterDataForExport(array $data): array
    {
        $columnsToKeep = $this->getColumnsToKeep();
        return array_intersect_key($data, array_flip($columnsToKeep));
    }

    /**
     * Retrieves the columns to keep based on the global leads page settings.
     *
     * @return array
     */
    private function getColumnsToKeep(): array
    {
        $settings = PageSetting::query()
                        ->where(['page' => 'global_leads', 'type' => 'customize_columns', 'user_id' => auth()->id()])
                        ->first();

        if ($settings && ! empty($settings->data)) {
            return $settings->data;
        }

        return $this->getSortFields();
    }

    /**
     * Returns the appropriate "whereIn" or "orWhereIn" method based on the given index.
     *
     * @param int $index
     * @return string
     */
    public function getWhereInMethod(int $index, bool $isRaw = false): string
    {
        if($isRaw){
            return ($index == 0) ? 'whereRaw' : 'orWhereRaw';
        }
        return ($index == 0) ? 'whereIn' : 'orWhereIn';
    }

    /**
     * Converts an array of Excel filters to SQL conditions for a given query.
     *
     * @param Builder $query
     * @param array $excelFilters
     * @return Builder
     */
    public function convertExcelFilterToSql(Builder $query, array $excelFilters)
    {
        return $query->where(function ($query) use ($excelFilters) {
            foreach ($excelFilters as $key => $filter) {

                $isJsonColumn = ! empty($filter['is_json_column']);
                $column = $filter['column'];
                $values = $filter['values'];

                if(! $isJsonColumn){
                    $query->whereIn($column, $values);
                    continue;
                }

                $values = array_map('strtolower', $values);
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $query->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.' . $column . '"))) IN (' . $placeholders . ')', $values);
            }
        });
    }

    /**
     * Converts an array of filter conditions to a SQL query using Laravel's query builder.
     *
     * @param Builder $query
     * @param array $conditions
     * @return Builder
     */
    public function convertFilterToSql(Builder $query, array $conditions)
    {
        return $query->where(function ($query) use ($conditions) {
            foreach ($conditions as $conditionKey => $conditionGroup) {

                $query->where(function ($query2) use ($conditionGroup) {

                    foreach ($conditionGroup as $index => $condition) {

                        $type = $this->getConditionType($condition['operator']);

                        if(in_array($condition['operator'], ['=', '!='])) {
                            $type = null;
                        }

                        $method = $this->getConditionMethod($index, $type);

                        if($type) {
                            $query2->$method($condition['column']);
                            continue;
                        }

                        $query2->$method($condition['column'], $condition['operator'], $condition['value']);
                    }
                });
            }
        });
    }

    /**
     * Converts an array of relational filter conditions to a SQL query using Laravel's query builder.
     *
     * @param Builder $query
     * @param array $relationalConditions
     * @return Builder
     */
    public function convertRelationalFilterToSql(Builder $query, array $relationalConditions): Builder
    {
        return $query->where(function ($query) use ($relationalConditions) {
            foreach ($relationalConditions as $conditionKey => $conditionGroup) {
                $query->where(function ($query2) use ($conditionGroup) {
                    foreach ($conditionGroup as $index => $condition) {
                        $method = $this->getConditionMethod($index);
                        $query2->$method($condition['column'], $condition['operator'], $condition['value']);
                    }
                });
            }
        });
    }

    /**
     * Updates the leads in the database based on the provided request.
     *
     * @param Request $request
     * @return void
     */
    public function formatAndUpdateLeads(Request $request): void
    {
        $leads = collect($request->leads);
        $leadIds = $leads->pluck('id')->all();
        $updatedLeadsData = [];
        $updatableFields = ['datas'];

        $buyerIntegrationIds = $leads->pluck('buyer_integration_id')->all();
        $integrations = Integration::whereIn('id', $buyerIntegrationIds)
                            ->select('id', 'buyer_id', 'buyer_unique_id')
                            ->get();

        $leadData = PlatformData::whereIn('id', $leadIds)->select('id', 'datas')->get();

        $this->formatLeads($leadData, $leads, $updatedLeadsData, $updatableFields, $integrations);

        if(count($updatedLeadsData) > 0){
            PlatformData::upsert(
                $updatedLeadsData,
                ['id'],
                array_unique($updatableFields)
            );
        }

        $this->updateLeadReports($updatedLeadsData);
        $this->updateLeadLogs($leads);

        // GlobalPostBackTriggerJob::dispatch([
        //     'type' => 'bulk_retainer',
        //     'lead_ids' => $leadData->pluck('id')->all()
        // ], 'on_lead_update');
    }

    /**
     * Updates the lead logs for a collection of leads.
     *
     * @param Collection $leads
     * @return void
     */
    public function updateLeadLogs(Collection $leads): void
    {
        $leadIds = $leads->pluck('id')->all();

        $formattedData = [];

        $leadLogs = LeadLog::whereIn('lead_id', $leadIds)
                        ->select('id', 'lead_id', 'log_data')
                        ->get()
                        ->groupBy('lead_id');

        foreach($leads as $lead){

            if(empty($leadLogs[$lead['id']])) continue;

            $leadLog = $leadLogs[$lead['id']][0];
            $logData = $leadLog->log_data;
            $originalPayload = $logData && $logData['original_payload'] ? $logData['original_payload'] : null;
            if(empty($originalPayload)) continue;

            foreach($lead as $key => $value){
                if(! array_key_exists($key, $originalPayload)) continue;
                $originalPayload[$key] = $value;
            }

            $logData['original_payload'] = $originalPayload;

            $formattedData[] = [
                'id' => $leadLog->id,
                'log_data' => json_encode($logData)
            ];
        }

        LeadLog::upsert(
            $formattedData,
            ['id'],
            ['log_data']
        );
    }

    /**
     * Updates the lead reports in the database based on the provided updated leads data.
     *
     * @param array $updatedLeadsData
     * @return void
     */
    public function updateLeadReports(array $updatedLeadsData): void
    {
        $reportFields = ['affid', 'buyer_integration_id', 'buyer_id'];
        $updatedLeadsData = collect($updatedLeadsData);
        $leadIds = $updatedLeadsData->pluck('id')->all();
        $leads = $updatedLeadsData->select(['id', ...$reportFields]);
        $formattedReports = [];

        $reports = LeadReport::query()
                    ->whereIn('lead_id', $leadIds)
                    ->select('id', 'lead_id', ...$reportFields)
                    ->get()
                    ->groupBy('lead_id');

        foreach($reports as $key => $reportData){
            $lead = $leads->firstWhere('id', $key); // Requested Leads
            if(empty($lead)) continue;

            unset($lead['id']);

            $formattedReport = collect($reportData)
                                    ->map(function($item) use ($lead) {
                                        unset($item->lead_id);
                                        return array_merge($item->toArray(), $lead);
                                    })
                                    ->all();

            array_push($formattedReports, ... $formattedReport);
        }

        if(empty($formattedReports)) return;

        LeadReport::upsert(
            $formattedReports,
            ['id'],
            $reportFields
        );
    }

    /**
     * Formats an array of lead data and updates the given arrays with the formatted data.
     *
     * @param EloquentCollection $leadData
     * @param Collection $leads
     * @param array
     * @param array
     * @return void
     */
    public function formatLeads(
        EloquentCollection $leadData,
        Collection $leads,
        array &$updatedLeadsData,
        array &$updatableFields,
        EloquentCollection $integrations
    ): void
    {
        $integrationsGrouped = $integrations->groupBy('id');

        foreach ($leadData as $lead) {
            $newLead = $leads->firstWhere('id', $lead->id);
            unset($newLead['id']);

            $formattedLead = [
                'id' => $lead->id,
                'datas' => array_merge($lead->datas, $newLead),
            ];

            foreach (['email', 'phone', 'affid', 'buyer_integration_id', 'page_source'] as $field) {

                if (! array_key_exists($field, $newLead)) continue;

                $formattedLead[$field] = $newLead[$field] ?? '';
                $updatableFields[] = $field;
            }

            $isBuyerIntegrationId = array_key_exists('buyer_integration_id', $formattedLead);

            if($isBuyerIntegrationId){
                $buyerIntegrationId = $formattedLead['buyer_integration_id'] ?? null;
                $integration = ! empty($integrationsGrouped[$buyerIntegrationId]) ? $integrationsGrouped[$buyerIntegrationId][0] : null;
                $formattedLead['buyer_id'] = $integration ? $integration->buyer_id : null;
                $formattedLead['datas']['lead_buyer'] = $integration ? $integration->buyer_unique_id : null;
                $updatableFields[] = 'buyer_id';
            }

            $formattedLead['datas'] = json_encode($formattedLead['datas']);
            $updatedLeadsData[] = $formattedLead;
        }
    }

    /**
     * Updates the 'created_at' field of a lead report in the database with the given report ID and date.
     *
     * @param int $reportId
     * @param string $date
     * @return void
     */
    public function updateReportData(LeadReport $report, Request $request, array $formattedData, bool $isCreate = false, PlatformData $platformData = null): void
    {
        $leadData = [
            'retained_date' => null,
            'returned_date' => null,
            'is_retainer' => $formattedData['is_retainer'],
            'is_returned' => $formattedData['is_returned']
        ];

        $isReportUpdatable = (bool) $formattedData['is_retainer'] || (bool) $formattedData['is_returned'];

        $date = empty($request->created_at) ? now() : Carbon::parse($request->created_at)->startOfDay();

        if(! empty($request->is_retainer) && ! empty($request->created_at)){
            $date = Carbon::parse($request->created_at)->midDay();
            $leadData['retained_date'] = $date;
        }

        if(! empty($request->is_returned) && ! empty($request->created_at)){
            $date = Carbon::parse($request->created_at)->midDay();
            $leadData['returned_date'] = $date;
        }

        if($platformData->created_at && ($platformData->created_at->greaterThan(Carbon::parse($request->created_at)))){
            $date = $platformData->created_at->addHour();
        }

        if(empty($request->is_retainer)){
            $leadData['retained_date'] = null;
        }

        if(empty($request->is_returned)){
            $leadData['returned_date'] = null;
        }

        DB::table('lead_reports')
            ->where('id', $report->id)
            ->where('created_at', '!=', $date)
            ->update(['created_at' => $date]);

        if($isReportUpdatable || ($request->is_retainer || $request->is_returned)){
            $this->updatePlatformData($request->lead_id, $leadData);
        }

        // if(! empty($request->is_returned)) {
        //     $leadData['returned_date'] = $date;
        //     $leadData['is_returned'] = 1;
        //     $this->updatePlatformData($request->lead_id, $leadData);
        // }
    }

    /**
     * Updates the status of a lead based on the given parameters.
     *
     * @param int $leadId
     * @param int $reportId
     * @param bool $isRetainer
     * @return void
     */
    public function updateLeadStatus(int $leadId, int $reportId, bool $isRetainer, ?string $leadStatus = null, int $isReturned = 0): void
    {
        if($isRetainer || $isReturned){
            $this->updatePlatformData($leadId, [
                'lead_status' => $isRetainer ? 'Retained' : 'Returned'
            ]);
            return;
        }

        // $hasReturned = $this->hasAnyReturnedLead($leadId, $reportId);

        $isRetainedOrReturned = $this->hasAnyRetainedLead($leadId, $reportId);
        if($isRetainedOrReturned) return;

        $this->updatePlatformData($leadId, [
            'lead_status' => $leadStatus ?: 'Pending',
            'retained_date' => null,
            'returned_date' => null,
            'is_retainer' => 0,
            'is_returned' => 0
        ]);
    }

    /**
     * Checks if there is any retained lead report for given lead ID,
     * excluding the given report ID if it is not empty.
     *
     * @param int $leadId
     * @param int $reportId
     *
     * @return bool
     */
    public function hasAnyRetainedLead(int $leadId, int | null $reportId = null): bool
    {
        return LeadReport::query()
                ->when(! empty($reportId), function($query) use ($reportId) {
                    return $query->where('id', '!=', $reportId);
                })
                ->where('lead_id', $leadId)
                ->where(function($query) {
                    $query->where('is_retainer', '>', 0)
                        ->orWhere('is_returned', '>', 0);
                })
                ->exists();
    }

    /**
     * Checks if there is any returned lead report for given lead ID,
     * excluding the given report ID if it is not empty.
     *
     * @param int $leadId
     * @param int $reportId
     *
     * @return bool
     */
    public function hasAnyReturnedLead(int $leadId, int | null $reportId = null): bool
    {
        return LeadReport::query()
                ->when(! empty($reportId), function($query) use ($reportId) {
                    return $query->where('id', '!=', $reportId);
                })
                ->where('lead_id', $leadId)
                ->where('is_returned', '>', 0)
                ->exists();
    }


    /**
     * Retrieves the retained lead report associated with the given lead ID.
     *
     * @param int $leadId
     * @return LeadReport|null
     */
    public function getRetainedLeadReport(int $leadId): LeadReport | null
    {
        return LeadReport::query()
                    ->where('lead_id', $leadId)
                    ->where('is_retainer', '>', 0)
                    ->first();
    }

    /**
     * Formats the report request data.
     *
     * @param array $requestData
     * @return array
     */
    public function formatReportRequest(array $requestData): array
    {
        if(! empty($requestData['show_in_portal'])){
            $requestData['is_retainer'] = 2;
        }

        unset($requestData['show_in_portal']);

        if(! array_key_exists('lead_status', $requestData)){
            unset($requestData['lead_status']);
        }

        return $requestData;
    }

    /**
     * Updates the revenue and payout for a lead in the database.
     *
     * @param int $leadId The ID of the lead.
     * @return void
     */
    public function updateRevenuePayout(int $leadId): void
    {
        $report = LeadReport::where('lead_id', $leadId)
                    ->select(
                        DB::raw("SUM(lead_revenue) as revenue"),
                        DB::raw("SUM(affiliate_payout) as payout")
                    )
                    ->groupBy('lead_id')
                    ->first();

        $this->updatePlatformData($leadId, [
            'revenue' => $report ? (float) $report->revenue : 0,
            'payout' => $report ? (float) $report->revenue - (float) $report->payout : 0
        ]);
    }

    /**
     * Updates the platform data for a given ID.
     *
     * @param int $id The ID of the platform data.
     * @param mixed $data The data to update.
     * @return void
     */
    public function updatePlatformData(int $id, $data): void
    {
        PlatformData::where('id', $id)->update($data);
    }

    /**
     * Formats an advance condition for a lead report query.
     *
     * If the condition's rule is 'equals' or 'not_equals', the condition's value is compared
     * to a column in the platform data table. Otherwise, the condition's value is compared
     * to a column in the related table specified by the condition's column.
     *
     * @param array $conditions The condition to be formatted.
     *                          The array should have the following keys:
     *                          - 'rule': The rule of the condition.
     *                          - 'column': The column of the condition.
     *                          - 'value': The value of the condition.
     * @return array
     */
    public function formatAdvanceConditionToSql(array $conditions): array
    {
        if(in_array($conditions['rule'], ['equals', 'not_equals'])){
            $dbColumns = [
                'list_name' => 'platform_datas.list_id',
                'buyer_name' => 'platform_datas.buyer_id',
                'buyer_integration' => 'platform_datas.buyer_integration_id',
                'affiliate_name' => 'platform_datas.affiliate_id',
                'affid' => 'platform_datas.affid'
            ];

            return $this->convertConditionToSql([
                'column' => $dbColumns[$conditions['column']],
                'rule' => $conditions['rule'],
                'value' => $conditions['value']
            ], isRelational: true);
        }

        $columns = [
            'list_name' => function ($query) {
                return $query->select('name')
                    ->from('platform_lists')
                    ->whereColumn('platform_lists.id', 'platform_datas.list_id')
                    ->limit(1);
            },
            'buyer_name' => function($query) {
                return $query->select('name')
                        ->from('buyers')
                        ->whereColumn('buyers.id', 'platform_datas.buyer_id')
                        ->limit(1);
            },
            'buyer_integration' => function($query) {
                return $query->select('name')
                        ->from('integrations')
                        ->whereColumn('integrations.id', 'platform_datas.buyer_integration_id')
                        ->limit(1);
            },
            'affiliate_name' => function($query) {
                return $query->select('name')
                        ->from('users')
                        ->whereColumn('users.id', 'platform_datas.affiliate_id')
                        ->limit(1);
            },
            'affid' => 'platform_datas.affid',
            'phone' => 'platform_datas.phone',
            'email' => 'platform_datas.email',
            'lead_status' => 'platform_datas.lead_status',
            'affm_lead_id' => 'platform_datas.affm_lead_id'
        ];

        return $this->convertConditionToSql([
            'column' => $columns[$conditions['column']],
            'rule' => $conditions['rule'],
            'value' => $conditions['value']
        ], isRelational: true);
    }

    /**
     * Converts the given type to an array of data.
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function convertTypeToData(Request $request, string $type): Collection
    {
        return match($type){
            'list_name' => $this->getPlatformList($request),
            'buyer_name' => $this->getBuyers($request),
            'buyer_integration' => $this->getBuyerIntegrations($request),
            'affiliate_name' => $this->getAffiliates($request),
            'affid' => $this->getAffIds($request),
            default => []
        };
    }

    /**
     * Retrieves a list of affids from LeadReport filtered by the search text if provided.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAffIds(Request $request): Collection
    {
        return LeadReport::select('affid as value', 'affid as label')
                ->whereNotNull('affid')
                ->when(! empty($request->search_txt), function ($query) use ($request) {
                    return $query->where('affid', 'like', '%'.$request->search_txt.'%');
                })
                ->groupBy('affid')
                ->limit(50)
                ->get();
    }

    /**
     * Retrieves a list of buyer integrations with associated platform list names, filtered by the search text if provided.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBuyerIntegrations(Request $request): Collection
    {
        return DB::table('integrations')
                ->leftJoin('platform_lists', 'integrations.list_id', '=', 'platform_lists.id')
                ->select('integrations.id as value', DB::raw("CONCAT(integrations.name , ' ( ', platform_lists.name, ' )') as label"))
                ->when(! empty($request->search_txt), function ($query) use ($request) {
                    return $query->where('integrations.name', 'like', '%'.$request->search_txt.'%');
                })
                ->limit(50)
                ->get();
    }

    /**
     * Retrieves a list of affiliates with associated IDs, filtered by the search text if provided.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAffiliates(Request $request): Collection
    {
        $affiliates = User::select('id as value', 'name as label', 'data->affids as affids', 'role')
                        ->when(!empty($request->search_txt), function ($query) use ($request) {

                            $searchTxt = "%{$request->search_txt}%";

                            return $query->where(function ($query) use ($searchTxt, $request) {

                                $affIds = explode(',', str_replace(' ', '', $request->search_txt));

                                return $query->where(function($query) use ($request, $affIds) {
                                    return $query->where('data->affids', 'like', '%' . $request->search_txt . '%')
                                                ->orWhereJsonContains('data->affids', $affIds);
                                })
                                ->when(hasAffiliateAccess(), function ($query) use ($request, $searchTxt) {
                                    return $query->orWhere('name', 'like', $searchTxt)
                                                ->orWhere('email', 'like', $searchTxt)
                                                ->orWhere('username', 'like', $searchTxt)
                                                ->orWhereHas('affiliate', function ($query) use ($searchTxt) {
                                                        $query->where('country', 'like', $searchTxt)
                                                        ->orWhere('company_name', 'like', $searchTxt);
                                                });

                                });
                            });

                        })
                        ->where('role', 'affiliate')
                        ->when(! empty($request->is_remote_search), function($query) {
                            return $query->limit(50);
                        })
                        ->get();

        return $affiliates->map(function ($user): array {
                    $affids = $user->affids ? implode(', ', json_decode($user->affids, true)) : '';

                    if(! hasAffiliateAccess() && $user->role === 'affiliate') {
                        return [
                            'value' => $user->value,
                            'label' => $affids
                        ];
                    }

                    return [
                        'value' => $user->value,
                        'label' => $user->label . ($affids ? ' (' . $affids . ')' : '')
                    ];
                });
    }

    /**
     * Retrieves a list of buyers with associated IDs, filtered by the search text if provided.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBuyers(Request $request): Collection
    {
        return DB::table('buyers')
                ->when(! empty($request->search_txt), function ($query) use ($request) {
                    return $query->where('name', 'like', '%'.$request->search_txt.'%');
                })
                ->select('id as value', 'name as label')->get();
    }

    /**
     * Retrieves a list of platform lists with associated IDs, filtered by the search text if provided.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPlatformList(Request $request): EloquentCollection
    {
        return PlatformList::select('id as value', 'name as label')
                ->when(! empty($request->search_txt), function ($query) use ($request) {
                    return $query->where('name', 'like', '%'.$request->search_txt.'%');
                })
                ->limit(50)
                ->get();
    }

    public function updateFilledData(Request $request)
    {
        $filledData = collect($request->filled_data);
        if(empty($filledData)) {
            throw new \Exception('No data provided');
        }

        $fillable = (new PlatformData())->getFillable();
        $selectableFields = collect($filledData[0]['conditional_keys'])->keys()
                                ->filter(function($key) use ($fillable) {
                                    return in_array($key, $fillable);
                                })
                                ->all();

        $updatableData = array_keys(($filledData[0]['updatable_data']));
        $isAmountField = in_array('revenue', $updatableData) || in_array('affiliate_payout', $updatableData);
        $updatableFields = collect(['datas', ...$updatableData])->all();

        $leads = PlatformData::query()
                    ->select('id', 'datas', ...$selectableFields)
                    ->when(! empty($isAmountField), function($query) {
                        return $query->addSelect([
                            'affiliate_id',
                            'list_id',
                            'buyer_id',
                            'affid',
                            'buyer_integration_id',
                            'affiliate_specs_id',
                            'affm_source_id'
                        ]);
                    })
                    ->where(function($query) use ($filledData) {
                        return $filledData->map(function($item, $key) use ($query) {
                            $method = $this->getConditionMethod($key);
                            return $query->$method($item['conditional_keys']);
                        });
                    })
                    ->get()
                    ->groupBy(function($item) use ($selectableFields) {
                        return collect($selectableFields)->map(function($field) use ($item) {
                            return $item[$field];
                        });
                    })->toArray();

        if(empty($leads)) {
            throw new \Exception('No leads found');
        };

        $leadDataGenerator = $this->generateLeadData($filledData, $leads, $fillable);
        $allLeadData = iterator_to_array($leadDataGenerator, false);

        if(empty($allLeadData)) {
            throw new \Exception('No leads found');
        }

        $updateFields = collect($updatableFields)->filter(function($item) {
                            return ! in_array($item, ['revenue', 'affiliate_payout']);
                        })
                        ->all();

        $leadData = collect($allLeadData);
        $leadGroup = $leadData->groupBy('id')->all();
        $leadIds = $leadData->pluck('id')->all();

        $result = PlatformData::upsert(
            $allLeadData,
            ['id'],
            $updateFields
        );

        $this->updateRelevantReport( $updatableFields, $leadGroup, $leadIds );
        $this->updateRelevantLeadLog( $updatableFields, $leadGroup, $leadIds );
    }

    private function generateLeadData($filledData, $leads, $fillable) {

        foreach ($filledData as $item) {
            $arrayKey = json_encode(array_values($item['conditional_keys']));
            $groupLeads = $leads[$arrayKey] ?? [];
            if (empty($groupLeads)) continue;

            foreach ($groupLeads as &$lead) {
                $datas = $lead['datas'];

                foreach ($item['updatable_data'] as $key => $value) {

                    if (in_array($key, $fillable)) {
                        $lead[$key] = $value;
                    }

                    if (array_key_exists($key, $datas)) {
                        $datas[$key] = $value;
                    }
                }

                $lead['datas'] = json_encode($datas);
                yield $lead;
            }
        }
    }

    /**
     * Sets the buyer integration ID, buyer ID, and buyer unique ID in the given platform data and its associated data.
     *
     * @param PlatformData $lead
     * @param int|string $integrationId
     * @param array $datas
     * @return void
     */
    public function setBuyerIntegration(PlatformData &$lead, int | string $integrationId, array &$datas): void
    {
        $integration = Integration::query()
                            ->select('id', 'buyer_id', 'buyer_unique_id')
                            ->find($integrationId);

        if(empty($integration)) return;

        $lead->buyer_integration_id = $integration->id;
        $lead->buyer_id = $integration->buyer_id;
        $datas['lead_buyer'] = $integration->buyer_unique_id;
    }

    public function updateRelevantLeadLog(array $updatableFields, $leadGroup, $leadIds): void
    {
        $logs = LeadLog::whereIn('lead_id', $leadIds)
                    ->select('id', 'lead_id', 'log_data')
                    ->get();

        $logDataGenerator = $this->generatorLeadLogs($logs, $leadGroup, $updatableFields);
        $logData = iterator_to_array($logDataGenerator, false);

        if(empty($logData)) return;

        LeadLog::upsert(
            $logData,
            ['id'],
            ['log_data']
        );
    }

    private function generatorLeadLogs($logs, $leads, array $updatableFields)
    {
        foreach($logs as $log) {
            $lead = $leads[$log->lead_id][0] ?? null;
            if(empty($lead)) continue;

            $logData = $log->log_data;
            $originalPayload = $logData && $logData['original_payload'] ? $logData['original_payload'] : null;
            if(empty($originalPayload)) continue;

            foreach($updatableFields as $key){
                if(! array_key_exists($key, $originalPayload)) continue;
                $originalPayload[$key] = $lead[$key];
            }

            $logData['original_payload'] = $originalPayload;

            yield [
                'id' => $log->id,
                'log_data' => json_encode($logData)
            ];
        }
    }

    public function updateRelevantReport(array $updatableFields, $leadGroup, $leadIds): void
    {
        $updatableReportFields = collect($updatableFields)->filter(function($item) {
                                        return in_array($item, ['affid', 'revenue', 'affiliate_payout']);
                                    })
                                    ->map(function($item) {
                                        if($item === 'revenue') return 'lead_revenue';
                                        return $item;
                                    })
                                    ->all();

        if(empty($updatableReportFields)) return;

        $leadReports = LeadReport::whereIn('lead_id', $leadIds)
                            ->select(
                                'id',
                                'lead_id',
                                'affiliate_id',
                                'list_id',
                                'buyer_id',
                                'buyer_integration_id',
                                'affiliate_specs_id',
                                'affm_source_id',
                                'is_retainer',
                                'affid',
                                'lead_revenue',
                                'affiliate_payout',
                                'lead_profit',
                                'affiliate_margin',
                                'profit_margin'
                            )
                            ->get();

        if(empty($leadReports)) return;

        $leadRevenuePayouts = [];

        $leadReportDataGenerator = $this->generateLeadReportData($leadReports->toArray(), $leadGroup, $updatableReportFields, $leadRevenuePayouts);
        $leadReportData = iterator_to_array($leadReportDataGenerator, false);

        if(count($leadReportData) > 0) {
            LeadReport::upsert(
                $leadReportData,
                ['id'],
                $updatableReportFields
            );

            GlobalPostBackTriggerJob::dispatch([
                'type' => 'bulk_retainer',
                'lead_reports' => $leadReportData
            ], 'on_retainer_added');
        }

        if(count($leadRevenuePayouts) > 0) {
            PlatformData::upsert(
                $leadRevenuePayouts,
                ['id'],
                ['payout', 'revenue']
            );
        }
    }

    private function generateLeadReportData($leadReports, $leadGroup, $updatableReportFields, &$leadRevenuePayouts) {
        $lead = null;
        $isRetainer = false;

        $revenue = 0;
        $payout = 0;
        $totalReports = count($leadReports) - 1;

        foreach ($leadReports as $key => $report) {

            if(empty($lead) || ($lead && $lead['id'] != $report['lead_id'])) {

                if(! empty($lead) && ! $isRetainer) {
                    $newReport = $this->getFormData($lead);
                    $this->sumRevenuePayout($revenue, $payout, $newReport);
                    yield $newReport;
                }

                if(! empty($lead)) {
                    $leadRevenuePayouts[] = $this->getRevenuePayout($lead['id'], $revenue, $payout);;
                }

                $lead = $leadGroup[$report['lead_id']][0] ?? null;
                $this->resetInitialKeys($isRetainer, $revenue, $payout);
            }

            if (empty($lead)) continue;

            foreach ($updatableReportFields as $field) {
                if(in_array($field, ['lead_revenue', 'affiliate_payout'])) continue;
                $report[$field] = $lead[$field];
            }

            if(($lead['id'] === $report['lead_id']) && !empty($report['is_retainer'])) {

                if(array_key_exists('revenue', $lead)) {
                    $report['lead_revenue'] = $lead['revenue'];
                }

                if(array_key_exists('affiliate_payout', $lead)) {
                    $report['affiliate_payout'] = $lead['affiliate_payout'];
                }

                $amountFields = $this->calculateRevenuePayout($report['lead_revenue'], $report['affiliate_payout']);
                $report = array_merge($report, $amountFields);

                $isRetainer = true;
            }

            $this->sumRevenuePayout($revenue, $payout, $report);

            yield $report;

            if($key == $totalReports) {
                if(! empty($lead) && ! $isRetainer) {
                    $newReport = $this->getFormData($lead);
                    $this->sumRevenuePayout($revenue, $payout, $newReport);
                    yield $newReport;
                }

                if(! empty($lead)) {
                    $leadRevenuePayouts[] = $this->getRevenuePayout($lead['id'], $revenue, $payout);
                }

                $this->resetInitialKeys($isRetainer, $revenue, $payout);
            }
        }
    }

    private function sumRevenuePayout(&$revenue, &$payout, $report) {
        $revenue += (float) $report['lead_revenue'];
        $payout += (float) $report['affiliate_payout'];
    }

    private function resetInitialKeys(&$isRetainer, &$revenue, &$payout)
    {
        $isRetainer = false;
        $revenue = 0;
        $payout = 0;
    }

    private function getRevenuePayout($leadId, $revenue, $payout) {
        return [
            'id' => $leadId,
            'revenue' => $revenue,
            'payout' => $revenue - $payout
        ];
    }

    /**
     * Calculates the revenue, profit, affiliate margin and profit margin given the revenue and affiliate payout.
     *
     * @param float|int $revenue
     * @param float|int $affiliatePayout
     * @return array
     */
    public function calculateRevenuePayout( float | int $revenue = 0, float | int $affiliatePayout): array
    {
        $profit = $revenue - $affiliatePayout;
        $affiliateMargin = $revenue ? (($affiliatePayout / $revenue) * 100) : 0;
        $profitMargin = $revenue ? (($profit / $revenue) * 100) : 0;

        return [
            'lead_profit' => $profit,
            'affiliate_margin' => $affiliateMargin,
            'profit_margin' => $profitMargin
        ];
    }

    /**
     * Retrieves the form data for creating a new lead report from the given lead data and any revenue/payout data.
     *
     * @param PlatformData $lead
     * @param array $leadReportData
     * @return array
     */
    public function getFormData(array $lead): array
    {
        $formData = [
            'id' => null,
            'affiliate_id' => $lead['affiliate_id'] ?? null,
            'lead_id' => $lead['id'] ?? null,
            'list_id' => $lead['list_id'] ?? null,
            'buyer_id' => $lead['buyer_id'] ?? null,
            'affid' => $lead['affid'] ?? null,
            'buyer_integration_id' => $lead['buyer_integration_id'] ?? null,
            'affiliate_specs_id' => $lead['affiliate_specs_id'] ?? null,
            'affm_source_id' => $lead['affm_source_id'] ?? null,
            'is_retainer' => true,
            'lead_revenue' => $lead['revenue'] ?? 0,
            'affiliate_payout' => $lead['affiliate_payout'] ?? 0,
            'lead_profit' => 0,
            'affiliate_margin' => 0,
            'profit_margin' => 0
        ];

        $reportData = $this->calculateRevenuePayout((float) $formData['lead_revenue'], (float) $formData['affiliate_payout']);

        return array_merge($formData, $reportData);
    }

    /**
     * Formats the given integrations by grouping them by buyer unique ID and plucking the buyer headers.
     *
     * @param Request $request
     * @param Collection $integrations
     * @return array
     */
    public function formatIntegrations(Request $request, Collection $integrations): array
    {
        return $integrations
                ->groupBy('buyer_unique_id')
                ->map(fn ($group, $buyerUniqueId) => [
                    'buyer_unique_id' => $buyerUniqueId,
                    'buyer_headers' => $group
                        ->pluck('buyer_headers')
                        ->flatten(1)
                        ->unique()
                        ->values()
                        ->toArray(),
                ])
                ->values()
                ->toArray();
    }
}
