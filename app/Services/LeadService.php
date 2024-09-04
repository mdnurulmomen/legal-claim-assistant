<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\LeadLog;
use App\Models\LeadReport;
use App\Models\PageSetting;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
                $searchText = phone($searchText, 'US')->formatE164();
                $searchCol = 'platform_datas.phone';
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
                'lead_status' => [
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

                if(in_array($item['column'], ['list_name', 'buyer_name', 'buyer_integration', 'affiliate_name', 'affid'])){
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
            'contains' => $this->makeCondition($conditions['column'], 'like', ('%' . $conditions['value'] . '%'), $isRelational),
            'does_not_contain' => $this->makeCondition($conditions['column'], 'not like', ('%' . $conditions['value'] . '%'), $isRelational),
            'begins_with' => $this->makeCondition($conditions['column'], 'like', ($conditions['value'] . '%'), $isRelational),
            'does_not_begin_with' => $this->makeCondition($conditions['column'], 'not like', ($conditions['value'] . '%'), $isRelational),
            'greater_than' => $this->makeCondition($conditions['column'], '>', $conditions['value'], $isRelational),
            'less_than' => $this->makeCondition($conditions['column'], '<', $conditions['value'], $isRelational),
            'equals' => $this->makeCondition($conditions['column'], '=', $conditions['value'], $isRelational),
            'not_equals' => $this->makeCondition($conditions['column'], '!=', $conditions['value'], $isRelational),
            'exists' => $this->makeCondition($conditions['column'], 'exists', null, $isRelational),
            'does_not_exist' => $this->makeCondition($conditions['column'], 'does_not_exist', null, $isRelational),
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
    public function makeCondition(string $column, string $operator, string | int | null $value = null, bool $isRelational = false): array
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

                if(! $isJsonColumn){
                    $query->where($filter['column'], $filter['values']);
                    continue;
                }

                $column = $filter['column'];
                $values = array_map('strtolower', $filter['values']);
                $placeholders = implode(',', array_fill(0, count($filter['values']), '?'));
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
                if(! isset($originalPayload[$key])) continue;
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
    public function updateReportData(LeadReport $report, Request $request, array $formattedData): void
    {
        $leadData = [
            'retained_date' => null,
            'is_retainer' => $formattedData['is_retainer']
        ];

        $isReportUpdatable = $report->is_retainer != $formattedData['is_retainer'];
        $date = empty($request->created_at) ? now() : Carbon::parse($request->created_at)->startOfDay();

        if($isReportUpdatable && ! empty($request->is_retainer) && ! empty($request->created_at)){
            $date = Carbon::parse($request->created_at)->midDay();
            $leadData['retained_date'] = $date;
        }

        if($isReportUpdatable && empty($request->is_retainer)){
            $leadData['retained_date'] = null;
        }

        DB::table('lead_reports')->where('id', $report->id)
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

    public function hasAnyRetainedLead(int $leadId, int $reportId): bool
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
            'affid' => 'platform_datas.affid'
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
}
