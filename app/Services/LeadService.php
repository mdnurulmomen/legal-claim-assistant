<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\LeadLog;
use App\Models\LeadReport;
use App\Models\PageSetting;
use App\Models\PlatformData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
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
            return [];
        }

        $filters = json_decode($filters, true);
        $formattedFilters = [];

        foreach($filters as $value){
            $conditions = [];

            foreach($value as $item){
                $conditions[] = $this->convertConditionToSql($item);
            }

            if(count($conditions) > 0){
                $formattedFilters[] = $conditions;
            }
        }

        return $formattedFilters;
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
    public function convertConditionToSql(array $conditions): array
    {
        $condition = match($conditions['rule']){
            'contains' => $this->makeCondition($conditions['column'], 'like', '%' . $conditions['value'] . '%'),
            'does_not_contain' => $this->makeCondition($conditions['column'], 'not like', '%' . $conditions['value'] . '%'),
            'begins_with' => $this->makeCondition($conditions['column'], 'like', $conditions['value'] . '%'),
            'does_not_begin_with' => $this->makeCondition($conditions['column'], 'not like', $conditions['value'] . '%'),
            'greater_than' => $this->makeCondition($conditions['column'], '>', $conditions['value']),
            'less_than' => $this->makeCondition($conditions['column'], '<', $conditions['value']),
            'equals' => $this->makeCondition($conditions['column'], '=', $conditions['value']),
            'not_equals' => $this->makeCondition($conditions['column'], '!=', $conditions['value']),
            'exists' => $this->makeCondition($conditions['column'], 'exists'),
            'does_not_exist' => $this->makeCondition($conditions['column'], 'does_not_exist'),
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
    public function makeCondition(string $column, string $operator, string | int | null $value = null)
    {
        return [
            'column' => "platform_datas.datas->" . $column,
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
    public function convertExcelFilterToSql(Builder $query, array $excelFilters, )
    {
        return $query->where(function ($query) use ($excelFilters) {
            foreach ($excelFilters as $key => $filter) {

                $isJsonColumn = ! empty($filter['is_json_column']);
                $method = $this->getWhereInMethod($key, $isJsonColumn);

                if(! $isJsonColumn){
                    $query->$method($filter['column'], $filter['values']);
                    continue;
                }

                $column = $filter['column'];
                $values = array_map('strtolower', $filter['values']);
                $placeholders = implode(',', array_fill(0, count($filter['values']), '?'));
                $query->$method('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.' . $column . '"))) IN (' . $placeholders . ')', $values);
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

                $method = $this->getConditionMethod($conditionKey);

                $query->$method(function ($query2) use ($conditionGroup) {

                    foreach ($conditionGroup as $index => $condition) {

                        $type = $this->getConditionType($condition['operator']);
                        $method2 = $this->getConditionMethod($index, $type);

                        if($type) {
                            $query2->$method2($condition['column']);
                            continue;
                        }

                        $query2->$method2($condition['column'], $condition['operator'], $condition['value']);
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
    public function formatAndUpdateLeads(Request $request)
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

        // info(json_encode(array_unique($updatableFields)));

        $this->updateLeadReports($updatedLeadsData);
        $this->updateLeadLogs($leads);

        // abort(400, 'Custom Error');
    }

    /**
     * Updates the lead logs for a collection of leads.
     *
     * @param Collection $leads
     * @return void
     */
    public function updateLeadLogs(Collection $leads)
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

    public function updateLeadReports(array $updatedLeadsData)
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
    )
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
                $integration = ! empty($integrationsGrouped[$buyerIntegrationId]) ?$integrationsGrouped[$buyerIntegrationId][0] : null;
                $formattedLead['buyer_id'] = $integration ? $integration->buyer_id : null;

                if(! empty($integration) && isset($formattedLead['datas']['lead_buyer'])){
                    $formattedLead['datas']['lead_buyer'] = $integration->buyer_unique_id;
                }
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
    public function updateReportData(int $reportId, string $date): void
    {
        DB::table('lead_reports')->where('id', $reportId)->update(['created_at' => $date]);
    }

}
