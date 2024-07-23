<?php

namespace App\Services;

use App\Models\PageSetting;
use App\Models\PlatformData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    public function formatExcelFilters(Request $request)
    {
        //Buyer Integration, Buyer name, Affiliate name, Lead Status

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

    public function makeConditionWithoutOperator(string $column, array $values = [])
    {
        return [
            'column' => "platform_datas.datas->" . $column,
            'values' => $values
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
    public function getWhereInMethod(int $index): string
    {
        return ($index == 0) ? 'whereIn' : 'orWhereIn';
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

        $leadData = PlatformData::whereIn('id', $leadIds)->select('id', 'datas')->get();
        $updatableFields = ['datas'];

        foreach ($leadData as $lead) {
            $newLead = $leads->firstWhere('id', $lead->id);
            unset($newLead['id']);

            $formattedLead = [
                'id' => $lead->id,
                'datas' => json_encode(array_merge($lead->datas, $newLead)),
            ];

            foreach (['email', 'phone', 'affid'] as $field) {
                if (! empty($newLead[$field])) {
                    $formattedLead[$field] = $newLead[$field];
                    $updatableFields[] = $field;
                }
            }

            $updatedLeadsData[] = $formattedLead;
        }

        PlatformData::upsert(
            $updatedLeadsData,
            ['id'],
            array_unique($updatableFields)
        );
    }

}
