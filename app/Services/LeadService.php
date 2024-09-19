<?php

namespace App\Services;

use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use App\Models\Integration;
use App\Models\LeadLog;
use App\Models\LeadReport;
use App\Models\PageSetting;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadService extends ReportingService
{
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
                'headerName' => ucwords(str_replace('_', ' ', $header)),
                'minWidth' => 200,
                'hide' => ! in_array($header, $serialization),
                'editable' => true
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
                            (SUM(lead_reports.affiliate_payout) / COUNT(DISTINCT platform_datas.id)) as avg_affiliate_payout,
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

            return $query->orWhereRaw('LOWER(datas) like ?', ["%{$searchText}%"]);

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
    public function makeConditionWithoutOperator(string $column, array $values = [])
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

        $filters = json_decode($filters, true);
        $formattedFilters = [];
        $relationalConditions = [];

        foreach($filters as $value){
            $conditions = [];
            $relationalTerms = [];

            foreach($value as $item){
                if(empty($item['column']) || empty($item['rule']) || empty($item['value'])){
                    continue;
                }

                if(in_array($item['column'], ['list_name', 'buyer_name', 'buyer_integration', 'affiliate_name', 'affid', 'lead_status', 'phone', 'email'])){
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
    public function makeConditions(string $column, string $operator, string | int | null $value = '', bool $isRelational = false): array
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
                    $query->where($column, $values);
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
    public function updateReportData(LeadReport $report, Request $request, array $formattedData, bool $isCreate = false): void
    {
        $leadData = [
            'retained_date' => null,
            'is_retainer' => $formattedData['is_retainer']
        ];

        $isReportUpdatable = ($report->is_retainer != $formattedData['is_retainer']) || ($isCreate && $formattedData['is_retainer']);

        $date = empty($request->created_at) ? now() : Carbon::parse($request->created_at)->startOfDay();

        if($isReportUpdatable && ! empty($request->is_retainer) && ! empty($request->created_at)){
            $date = Carbon::parse($request->created_at)->midDay();
            $leadData['retained_date'] = $date;
        }

        if($isReportUpdatable && empty($request->is_retainer)){
            $leadData['retained_date'] = null;
        }

        DB::table('lead_reports')
            ->where('id', $report->id)
            ->where('created_at', '!=', $date)
            ->update(['created_at' => $date]);

        if($isReportUpdatable){
            $this->updatePlatformData($request->lead_id, $leadData);
        }
    }

    /**
     * Updates the status of a lead based on the given parameters.
     *
     * @param int $leadId
     * @param int $reportId
     * @param bool $isRetainer
     * @return void
     */
    public function updateLeadStatus(int $leadId, int $reportId, bool $isRetainer): void
    {
        if($isRetainer){
            $this->updatePlatformData($leadId, ['lead_status' => 'Retained']);
            return;
        }

        $isRetained = $this->hasAnyRetainedLead($leadId, $reportId);
        if($isRetained) return;

        $this->updatePlatformData($leadId, [
            'lead_status' => 'Pending',
            'retained_date' => null
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
                ->where('is_retainer', '>', 0)
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
            'revenue' => (float) $report->revenue,
            'payout' => (float) $report->revenue - (float) $report->payout
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
            'list_name' => 'platform_lists.name',
            'buyer_name' => 'buyers.name',
            'buyer_integration' => 'integrations.name',
            'affiliate_name' => 'users.name',
            'affid' => 'platform_datas.affid',
            'phone' => 'platform_datas.phone',
            'email' => 'platform_datas.email',
            'lead_status' => 'platform_datas.lead_status'
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
        return User::select('id as value', 'name as label')
                ->when(! empty($request->search_txt), function ($query) use ($request) {
                    return $query->where('name', 'like', '%'.$request->search_txt.'%');
                })
                ->where('role', 'affiliate')->get();
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

    /**
     * Updates the platform data for each lead that matches the given conditions.
     *
     * The conditions are given as an array of arrays, where each inner array
     * contains the following keys:
     * - 'conditional_keys': An array of keys to search in the platform data.
     * - 'updatable_data': An array of key-value pairs of data to update in the
     *                     platform data.
     *
     * The function uses the lazy collection to iterate over the results of the
     * where query, and for each result, it calls the savePlatformData method to
     * update the platform data.
     *
     * @param Request $request
     * @return void
     */
    public function updateFilledData(Request $request)
    {
        $filledData = $request->filled_data;
        $fillable = (new PlatformData())->getFillable();

        foreach ($filledData as $item) {

            PlatformData::where(column: $item['conditional_keys'])
                ->lazy()
                ->each(callback: function (&$lead) use ($item, $fillable) {
                    $this->savePlatformData($lead, $item['updatable_data'], $fillable);
                });
        }
    }

    /**
     * Saves the given updatable data to the given platform data and its associated data.
     *
     * If the key is 'revenue' or 'affiliate_payout', it is added to $leadReportData and
     * saved to the associated lead report. Otherwise, if the key is in $fillable, it is
     * updated in the platform data. If the key is in the associated data, it is updated
     * there as well.
     *
     * If the key is 'buyer_integration', the buyer integration ID, buyer ID, and buyer
     * unique ID are set in the platform data and its associated data.
     *
     * @param PlatformData $lead
     * @param array $updatableData
     * @param array $fillable
     * @return void
     */
    public function savePlatformData(PlatformData &$lead, array $updatableData, array $fillable): void
    {
        $leadReportData = [];
        $datas = $lead->datas;

        foreach ($updatableData as $key => $value) {

            if(in_array($key, ['revenue', 'affiliate_payout'])) {
                $leadReportData[$key] = $value;
                continue;
            }

            if(in_array($key, $fillable)){
                $lead->{$key} = $value;
            }

            if(array_key_exists($key, $datas)) {
                $datas[$key] = $value;
            }
        }

        if(array_key_exists('buyer_integration', $updatableData)) {
            $this->setBuyerIntegration($lead, $updatableData['buyer_integration'], $datas);
        }

        $lead->datas = $datas;
        $lead->save();

        if(! empty($leadReportData)) {
            $this->saveLeadReportData($lead, $leadReportData);
        }

        $this->updateRelevantReport($lead);
        $this->updateRelevantLeadLog($lead, $updatableData);
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

    /**
     * Updates the relevant fields in the lead log for the given lead.
     *
     * Looks up the lead log for the given lead and updates the fields in the log data
     * that are also present in the updatable data.
     *
     * @param PlatformData $lead
     * @param array $updatableData
     * @return void
     */
    public function updateRelevantLeadLog(PlatformData $lead, array $updatableData): void
    {
        $leadLog = LeadLog::where('lead_id', $lead->id)->first();
        if(empty($leadLog)) return;

        $logData = $leadLog->log_data;
        $originalPayload = $logData && $logData['original_payload'] ? $logData['original_payload'] : null;

        if(empty($originalPayload)) return;

        foreach($updatableData as $key => $value){
            if(! array_key_exists($key, $originalPayload)) continue;
            $originalPayload[$key] = $value;
        }

        $logData['original_payload'] = $originalPayload;
        $leadLog->log_data = $logData;
        $leadLog->save();
    }

    /**
     * Updates the relevant fields in the lead report for the given lead.
     *
     * @param PlatformData $lead
     * @return void
     */
    public function updateRelevantReport(PlatformData $lead): void
    {
        LeadReport::where('lead_id', $lead->id)
            ->update([
                'affid' => $lead->affid,
                'buyer_integration_id' => $lead->buyer_integration_id,
                'buyer_id' => $lead->buyer_id
            ]);
    }

    /**
     * Saves the lead report data for a given lead. If the lead report doesn't exist, a new one is created.
     * The revenue and affiliate payout are calculated based on the given data and the current values in the lead report.
     * The lead report is then updated with the new data and the revenue and payout totals are updated for the lead.
     *
     * @param PlatformData $lead
     * @param array $leadReportData
     * @return void
     */
    public function saveLeadReportData(PlatformData $lead, array $leadReportData): void
    {
        $leadReport = $this->getRetainedLeadReport($lead->id);
        if (empty($leadReport)) {
            $this->addNewLeadReport($lead, $leadReportData);
            return;
        }

        $revenue = array_key_exists('revenue', $leadReportData) ? $leadReportData['revenue'] : $leadReport->lead_revenue;
        $affiliatePayout = array_key_exists('affiliate_payout', $leadReportData) ? $leadReportData['affiliate_payout'] : $leadReport->affiliate_payout;

        $reportData = $this->calculateRevenuePayout((float) $revenue, (float) $affiliatePayout);

        $leadReport->update($reportData);
        $this->updateRevenuePayout($lead->id);
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
            'lead_revenue' => $revenue,
            'affiliate_payout' => $affiliatePayout,
            'lead_profit' => $profit,
            'affiliate_margin' => $affiliateMargin,
            'profit_margin' => $profitMargin
        ];
    }

    /**
     * Creates a new lead report and updates the lead status, revenue and payout accordingly.
     *
     * @param PlatformData $lead
     * @param array $leadReportData
     * @return void
     */
    public function addNewLeadReport(PlatformData $lead, array $leadReportData): void
    {
        $formData = $this->getFormData($lead, $leadReportData);
        $formattedData = $this->formatReportRequest($formData);
        $report = LeadReport::create($formattedData);

        $request = new Request($formattedData);

        $this->updateReportData($report, $request, $formattedData, isCreate: true);
        $this->updateLeadStatus($request->lead_id, $report->id, $request->is_retainer);
        $this->updateRevenuePayout($request->lead_id);
    }

    /**
     * Retrieves the form data for creating a new lead report from the given lead data and any revenue/payout data.
     *
     * @param PlatformData $lead
     * @param array $leadReportData
     * @return array
     */
    public function getFormData(PlatformData $lead, array $leadReportData): array
    {
        $formData = [
            'affiliate_id' => $lead->affiliate_id,
            'lead_id' => $lead->id,
            'list_id' => $lead->list_id,
            'buyer_id' => $lead->buyer_id,
            'affid' => $lead->affId,
            'buyer_integration_id' => $lead->buyer_integration_id,
            'affiliate_specs_id' => $lead->affiliate_specs_id,
            'affm_source_id' => $lead->affm_source_id,
            'is_retainer' => true,
            'is_paid' => false,
            'is_internal' => false,
            'is_posted' => false,
            'lead_revenue' => 0,
            'affiliate_payout' => 0,
            'lead_profit' => 0,
            'affiliate_margin' => 0,
            'profit_margin' => 0,
            'page_source' => '',
            'sold_type' => '',
            'created_at' => now(),
            'show_in_portal' => false,
        ];

        $revenue =  $leadReportData['revenue'] ?? 0;
        $affiliatePayout = $leadReportData['affiliate_payout'] ?? 0;
        $reportData = $this->calculateRevenuePayout((float) $revenue, (float) $affiliatePayout);

        return array_merge($formData, $reportData);
    }
}
