<?php

namespace App\Services;

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
}
