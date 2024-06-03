<?php

namespace App\Services;

use Illuminate\Http\Request;

class ReportingService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Formats the order by and order in parameters from the request.
     *
     * @param Request $request
     * @return array
     */
    public function formatOrderByIn(Request $request): array
    {
        $orderBy = $request->input('order_by', '');
        $orderIn = $request->input('order_in', '');

        if (! in_array($orderIn, ['asc', 'desc'])) {
            $orderIn = '';
        }

        $validOrderByColumns = [
            'affid',
            'buyer_name',
            'affiliate_name',
            'platform_name',
            'posted',
            'accepted',
            'rejected',
            'accepted_cpl',
            'acceptance_rate',
            'acceptance_rate',
            'revenue',
            'profit',
            'affiliate_payout',
            'revenue_per_lead',
            'average_profit',
            'affiliate_average_payout'
        ];

        if (! in_array($orderBy, $validOrderByColumns)) {
            $orderBy = '';
        }

        return ["sub.$orderBy", $orderIn];
    }

    /**
     * Formats the group by parameter from the request.
     *
     * @param Request $request The request object containing the group by parameter.
     * @return array The formatted group by array.
     */
    public function formatGroupBy(Request $request): array
    {
        $groupBy = $request->get('group_by', '');
        $groupBy = explode(',', $groupBy);

        if(empty($groupBy)){
            return ['lead_reports.list_id'];
        }

        $formattedGroupBy = array_map(function($item) {
            return 'lead_reports.' . $item;
        }, $groupBy);

        return $formattedGroupBy;
    }

    /**
     * Formats the reporting tabs from the request.
     *
     * @param array $tabs
     * @return array
     */
    public function formatReportingTabs(array $tabs): array
    {
        return array_map(function ($tab, $key) {
            return [
                'label' => $tab,
                'value' => $key,
                'checked' => $key === 'list_id'
            ];
        }, $tabs, array_keys($tabs));
    }

    public function formatFilters(Request $request): array
    {
        $filters = $request->input('filters', '');
        if(empty($filters)){
            return [];
        }

        $filters = json_decode($filters, true);

        $formattedRules = collect($filters)->map(function ($item) {
            return collect($item)->map(function ($subitem) {
                return $this->convertConditionToSql($subitem);
            });
        });

        return $formattedRules->toArray();
    }

    public function convertConditionToSql($conditions)
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

    public function makeCondition($column, $operator, $value = null)
    {
        return [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
    }

    public function getConditionMethod(int $index, string $type = null)
    {
        $method = $index == 0 ? 'where' : 'orWhere';

        if($type){
            $type == 'exists' ? ($method .= 'NotNull') : ($method .= 'Null');
        }

        return $method;
    }

    public function getConditionType(string $operator){
        return in_array($operator, ['exists', 'does_not_exist']) ? $operator : null;
    }

}
