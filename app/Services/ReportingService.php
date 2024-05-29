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

}
