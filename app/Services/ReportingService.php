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

}
