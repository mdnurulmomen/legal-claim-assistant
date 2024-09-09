<?php

namespace App\Services;

use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
            'integration_name',
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

        return [$orderBy, $orderIn];
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

        $relationalConditions = [];
        $formattedFilters = [];

        foreach($filters as $value){
            $conditions = [];
            $relationalTerms = [];

            foreach($value as $item){
                if(empty($item['column']) || empty($item['rule']) || empty($item['value'])){
                    continue;
                }
                if(in_array($item['column'], ['platform', 'buyer', 'buyer_integration', 'affiliate', 'affid'])){
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
     * Formats the advance condition to SQL.
     *
     * @param array $conditions The conditions to be formatted.
     *                         The array should have the following keys:
     *                         - 'rule': The rule of the condition.
     *                         - 'column': The column of the condition.
     *                         - 'value': The value of the condition.
     * @return array
     */
    public function formatAdvanceConditionToSql(array $conditions): array
    {
        if(in_array($conditions['rule'], ['equals', 'not_equals'])){
            $dbColumns = [
                'platform' => 'lead_reports.list_id',
                'buyer' => 'lead_reports.buyer_id',
                'buyer_integration' => 'lead_reports.buyer_integration_id',
                'affiliate' => 'lead_reports.affiliate_id',
                'affid' => 'lead_reports.affid'
            ];

            return $this->convertConditionToSql([
                'column' => $dbColumns[$conditions['column']],
                'rule' => $conditions['rule'],
                'value' => $conditions['value']
            ]);
        }

        $columns = [
            'platform' => 'pl.name',
            'buyer' => 'buyers.name',
            'buyer_integration' => 'integrations.name',
            'affiliate' => 'affiliate.name',
            'affid' => 'lead_reports.affid'
        ];

        return $this->convertConditionToSql([
            'column' => $columns[$conditions['column']],
            'rule' => $conditions['rule'],
            'value' => $conditions['value']
        ]);
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
    public function makeCondition(string $column, string $operator, string | int | null | array $value = null)
    {
        return [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
    }

    /**
     * Returns the condition method based on the given index and type.
     *
     * @param int $index
     * @param string|null $type
     * @return string
     */
    public function getConditionMethod(int $index, string $type = null): string
    {
        $method = $index == 0 ? 'where' : 'orWhere';

        $matchType = match($type){
            'exists' => 'NotNull',
            'does_not_exist' => 'Null',
            'equals' => 'In',
            'not_equals' => 'NotIn',
            default => null
        };

        if($matchType){
            $method .= $matchType;
        }

        return $method;
    }

    /**
     * Returns the condition type based on the given operator.
     *
     * @param string $operator
     * @return string|null
     */
    public function getConditionType(string $operator): ?string
    {
        return match($operator){
            'exists', 'does_not_exist' => $operator,
            '=' => 'equals',
            '!=' => 'not_equals',
            default => null
        };
        // return in_array($operator, ['exists', 'does_not_exist']) ? $operator : null;
    }

    public function getReportTotals(QueryBuilder $baseQuery, Request $request)
    {
        $leads = $baseQuery->lazyById(10000, 'id');
        $totals = [
            'platform_name' => 'Total',
            'posted' => $leads->sum('posted'),
            'accepted' => $leads->sum('accepted'),
            'rejected' => $leads->sum('rejected'),
            'accepted_cpl' => $leads->sum('accepted_cpl'),
            'revenue' => $leads->sum('revenue'),
            'profit' => $leads->sum('profit'),
            'affiliate_payout' => $leads->sum('affiliate_payout'),
            'revenue_per_lead' => $leads->sum('revenue_per_lead'),
            'average_profit' => $leads->sum('average_profit'),
            'affiliate_average_payout' => $leads->sum('affiliate_average_payout'),
            'acceptance_rate' => $leads->avg('acceptance_rate'),
            'acceptance_rate_cpl' => $leads->avg('acceptance_rate_cpl'),
        ];

        $totals = (object) $totals;

        return new ReportingResource($totals);
    }

    /**
     * Converts an array of relational conditions to a SQL query using Laravel's query builder.
     *
     * @param Builder $query
     * @param array $relations
     * @return Builder
     */
    public function convertRelationsToSql(Builder $query, array $relations): Builder
    {
        return $query->where(function ($query) use ($relations) {
            foreach ($relations as $conditionKey => $conditionGroup) {
                $query->where(function ($query2) use ($conditionGroup) {
                    foreach ($conditionGroup as $index => $condition) {

                        if(in_array($condition['operator'], ['=', '!='])) {
                            $this->convertEqualsOrNotEqualsCondition($query2, $condition, $index);
                            continue;
                        }

                        $method = $this->getConditionMethod($index);
                        $query2->$method($condition['column'], $condition['operator'], $condition['value']);
                    }
                });
            }
        });
    }

    /**
     * Converts an equals or not equals condition to a SQL query using Laravel's query builder.
     *
     * @param Builder $query
     * @param array $condition
     * @param int $index
     * @return Builder
     */
    public function convertEqualsOrNotEqualsCondition(Builder &$query, array $condition, int $index): Builder
    {
        $type = $this->getConditionType($condition['operator']);
        $method = $this->getConditionMethod($index, $type);
        $conditionValue = is_array($condition['value']) ? $condition['value'] : [$condition['value']];

        return $query->$method($condition['column'], $conditionValue);
    }
}
